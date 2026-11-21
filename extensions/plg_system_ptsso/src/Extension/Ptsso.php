<?php

/**
 * Pamperins Single Sign-On: log in to the website with the Pamperins CRM account
 * (OpenID Connect, authorization code flow + PKCE).
 *
 * Flow:
 *   1. "Log in with Pamperins" button  -> index.php?ptsso=login   (administrator/index.php?ptsso=login for the admin)
 *   2. We redirect to the CRM's authorization endpoint (state, nonce, PKCE S256)
 *   3. The CRM sends the browser back to index.php?ptsso=callback&code=...&state=...
 *      (handled in onAfterRoute, before Joomla's admin access check, which refuses com_ajax to guests in Joomla 6)
 *   4. We exchange the code at the token endpoint (server to server, TLS, client secret + PKCE verifier),
 *      check the ID token's issuer / audience / expiry / nonce and read the userinfo endpoint.
 *      (Per OIDC Core 3.1.3.7 the ID token's signature check may rely on the TLS connection to the token
 *      endpoint, because the token comes straight from it, not through the browser.)
 *   5. Find or create the website account, sync groups from the CRM role, and log in.
 *
 * The normal username/password login is untouched and keeps working as a fallback.
 *
 * Login code adapted from Joomla's own plg_system_webauthn (GPL).
 *
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Plugin\System\Ptsso\Extension;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Authentication\AuthenticationResponse;
use Joomla\CMS\Event\User\LoginButtonsEvent;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

final class Ptsso extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    private const SESSION = 'plg_system_ptsso';
    private const PROFILE_KEY = 'ptsso.sub';

    public static function getSubscribedEvents(): array
    {
        return [
            'onUserLoginButtons' => 'onUserLoginButtons',
            'onAfterRoute'       => 'onAfterRoute',
        ];
    }

    // ------------------------------------------------------------------ login button

    public function onUserLoginButtons(LoginButtonsEvent $event): void
    {
        $app = $this->getApplication();

        if (!$app instanceof CMSApplication || !$app->getIdentity() || !$app->getIdentity()->guest || !$this->enabledHere()) {
            return;
        }

        $this->loadLanguage();

        // Click handler in a script file (no inline JS, so it also works with a strict Content-Security-Policy)
        $wa = $app->getDocument()->getWebAssetManager();

        if (!$wa->assetExists('script', 'plg_system_ptsso.login')) {
            $wa->registerScript('plg_system_ptsso.login', 'plg_system_ptsso/login.js', [], ['defer' => true]);
        }

        $wa->useScript('plg_system_ptsso.login');

        // Joomla prints data-* attributes into the HTML unescaped, so build a URL that can't contain quotes
        $url = $this->endpointUrl('login') . '&return=' . rawurlencode(base64_encode($this->defaultReturn()));
        $url = str_replace(["'", '"', '<', '>', '\\'], '', $url);

        $event->addResult([[
            'label'   => $this->params->get('button_label', 'Log in with Pamperins'),
            'tooltip' => $this->params->get('button_label', 'Log in with Pamperins'),
            'id'      => 'ptsso-login-' . UserHelper::genRandomPassword(8),
            'class'   => 'ptsso-login-button',
            'data-ptsso-url' => $url,
        ]]);
    }

    // ------------------------------------------------------------------ endpoint: index.php?ptsso=login|callback

    public function onAfterRoute(Event $event): void
    {
        $app = $this->getApplication();

        if (!$app instanceof CMSApplication || !($app->isClient('site') || $app->isClient('administrator'))) {
            return;
        }

        $input = $app->getInput();
        $step  = $input->getCmd('ptsso', '');

        if ($step !== 'login' && $step !== 'callback') {
            return;
        }

        $this->loadLanguage();

        if (!$this->enabledHere()) {
            $this->fail('PLG_SYSTEM_PTSSO_ERR_NOT_CONFIGURED');
        }

        if ($input->getString('error')) {
            Log::add('SSO provider returned error: ' . $input->getString('error'), Log::WARNING, 'ptsso');
            $this->fail($input->getString('error') === 'access_denied' ? 'PLG_SYSTEM_PTSSO_ERR_DENIED' : 'PLG_SYSTEM_PTSSO_ERR_TOKEN');
        }

        if ($step === 'callback') {
            if (!$input->getString('code')) {
                $this->fail('PLG_SYSTEM_PTSSO_ERR_STATE');
            }

            $this->finishLogin($input->getString('code'), $input->getString('state'));

            return;
        }

        $this->startLogin($input->getBase64('return', ''));
    }

    private function startLogin(string $returnB64): void
    {
        $config = $this->discovery();
        $state  = bin2hex(random_bytes(16));
        $nonce  = bin2hex(random_bytes(16));
        $verify = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $return = base64_decode($returnB64, true) ?: '';

        $this->getApplication()->getSession()->set(self::SESSION, [
            'state'  => $state,
            'nonce'  => $nonce,
            'verify' => $verify,
            'return' => ($return && Uri::isInternal($return)) ? $return : $this->defaultReturn(),
            'time'   => time(),
        ]);

        $query = http_build_query([
            'response_type'         => 'code',
            'client_id'             => $this->params->get('client_id'),
            'redirect_uri'          => $this->endpointUrl('callback', true),
            'scope'                 => 'openid email profile',
            'state'                 => $state,
            'nonce'                 => $nonce,
            'code_challenge'        => rtrim(strtr(base64_encode(hash('sha256', $verify, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);

        $sep = str_contains($config['authorization_endpoint'], '?') ? '&' : '?';
        $this->getApplication()->redirect($config['authorization_endpoint'] . $sep . $query);
    }

    private function finishLogin(string $code, string $state): void
    {
        $app     = $this->getApplication();
        $session = $app->getSession();
        $saved   = (array) $session->get(self::SESSION, []);
        $session->clear(self::SESSION);       // one use only

        if (empty($saved['state']) || !hash_equals($saved['state'], $state) || time() - (int) ($saved['time'] ?? 0) > 900) {
            $this->fail('PLG_SYSTEM_PTSSO_ERR_STATE');
        }

        $config = $this->discovery();
        $methods = (array) ($config['token_endpoint_auth_methods_supported'] ?? ['client_secret_basic']);
        $useBasic = \in_array('client_secret_basic', $methods, true) || !\in_array('client_secret_post', $methods, true);
        $tokens = $this->postForm($config['token_endpoint'], $useBasic, [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->endpointUrl('callback', true),
            'code_verifier' => $saved['verify'],
            'client_id'     => (string) $this->params->get('client_id'),
            'client_secret' => (string) $this->params->get('client_secret'),
        ]);

        if (empty($tokens['id_token']) || empty($tokens['access_token'])) {
            $this->fail('PLG_SYSTEM_PTSSO_ERR_TOKEN', 'token response without id_token/access_token');
        }

        $claims = $this->checkIdToken($tokens['id_token'], $config['issuer'], (string) $saved['nonce']);
        $info   = $this->getJson($config['userinfo_endpoint'] ?? '', $tokens['access_token']);

        if ($info) {
            if (($info['sub'] ?? null) !== $claims['sub']) {
                $this->fail('PLG_SYSTEM_PTSSO_ERR_TOKEN', 'userinfo sub mismatch');
            }

            $claims = array_merge($claims, $info);
        }

        $userId = $this->findOrCreateUser($claims);
        $user   = $this->users()->loadUserById($userId);

        if ($user->block || !empty($user->activation)) {
            $this->fail('PLG_SYSTEM_PTSSO_ERR_BLOCKED');
        }

        if ($app->isClient('administrator') && !$user->authorise('core.login.admin')) {
            $this->fail('PLG_SYSTEM_PTSSO_ERR_NO_ADMIN');
        }

        $this->loginUser($user);
        $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_PTSSO_WELCOME', htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8')));
        $app->redirect($saved['return'] ?: $this->defaultReturn());
    }

    // ------------------------------------------------------------------ token checks

    private function checkIdToken(string $jwt, string $issuer, string $nonce): array
    {
        $parts = explode('.', $jwt);

        if (\count($parts) !== 3) {
            $this->fail('PLG_SYSTEM_PTSSO_ERR_TOKEN', 'malformed id_token');
        }

        $claims = json_decode($this->b64url($parts[1]), true);
        $aud    = (array) ($claims['aud'] ?? []);
        $now    = time();

        $ok = \is_array($claims)
            && ($claims['iss'] ?? '') === $issuer
            && \in_array((string) $this->params->get('client_id'), $aud, true)
            && (\count($aud) === 1 || ($claims['azp'] ?? '') === (string) $this->params->get('client_id'))
            && (int) ($claims['exp'] ?? 0) > $now - 60
            && (int) ($claims['iat'] ?? $now) < $now + 300
            && hash_equals($nonce, (string) ($claims['nonce'] ?? ''))
            && !empty($claims['sub']);

        if (!$ok) {
            $this->fail('PLG_SYSTEM_PTSSO_ERR_TOKEN', 'id_token claims check failed');
        }

        return $claims;
    }

    private function b64url(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - \strlen($s) % 4) % 4));
    }

    // ------------------------------------------------------------------ accounts

    private function findOrCreateUser(array $c): int
    {
        $db  = $this->getDatabase();
        $sub = (string) $c['sub'];

        $key   = self::PROFILE_KEY;
        $value = json_encode($sub);
        $q     = $db->getQuery(true)
            ->select($db->quoteName('user_id'))
            ->from($db->quoteName('#__user_profiles'))
            ->where($db->quoteName('profile_key') . ' = :k')
            ->where($db->quoteName('profile_value') . ' = :v')
            ->bind(':k', $key)
            ->bind(':v', $value);
        $userId = (int) $db->setQuery($q)->loadResult();

        $email    = trim((string) ($c['email'] ?? ''));
        $verified = filter_var($c['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$userId && $email && $verified && $this->params->get('link_by_email', 1)) {
            $q = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__users'))
                ->where('LOWER(' . $db->quoteName('email') . ') = LOWER(:e)')
                ->bind(':e', $email);
            $userId = (int) $db->setQuery($q)->loadResult();

            if ($userId) {
                $this->saveSub($userId, $sub);
                Log::add("SSO: linked existing account $userId to sub $sub by verified e-mail", Log::INFO, 'ptsso');
            }
        }

        if (!$userId) {
            if (!$this->params->get('auto_create', 1)) {
                $this->fail('PLG_SYSTEM_PTSSO_ERR_NO_ACCOUNT');
            }

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->fail('PLG_SYSTEM_PTSSO_ERR_TOKEN', 'no usable e-mail for new account');
            }

            // Someone else's website account already uses this e-mail (and we couldn't link it: e-mail not verified)
            $q = $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__users'))
                ->where('LOWER(' . $db->quoteName('email') . ') = LOWER(:e2)')->bind(':e2', $email);

            if ((int) $db->setQuery($q)->loadResult()) {
                $this->fail('PLG_SYSTEM_PTSSO_ERR_EMAIL_IN_USE', "e-mail $email already used by another account and not verified at the provider");
            }

            $user = new User();
            $data = [
                'name'      => trim((string) ($c['name'] ?? '')) ?: strstr($email, '@', true),
                'username'  => $this->uniqueUsername((string) ($c['preferred_username'] ?? strstr($email, '@', true))),
                'email'     => $email,
                'password'  => UserHelper::genRandomPassword(32),
                'block'     => 0,
                'activation' => '',
                'groups'    => [(int) $this->params->get('group_client', 2)],
            ];
            $user->bind($data);

            if (!$user->save()) {
                $this->fail('PLG_SYSTEM_PTSSO_ERR_TOKEN', 'could not create account: ' . $user->getError());
            }

            $userId = (int) $user->id;
            $this->saveSub($userId, $sub);
            Log::add("SSO: created account $userId for sub $sub", Log::INFO, 'ptsso');
        }

        $this->syncUser($userId, $c);

        return $userId;
    }

    /** Keep name/e-mail current and set the admin/staff groups from the CRM role. */
    private function syncUser(int $userId, array $c): void
    {
        $user    = $this->users()->loadUserById($userId);
        $roles   = array_map('strval', (array) ($c[$this->params->get('roles_claim', 'roles')] ?? []));
        $managed = [
            (int) $this->params->get('group_admin', 7) => \in_array((string) $this->params->get('role_admin', 'admin'), $roles, true),
            (int) $this->params->get('group_staff', 6) => \in_array((string) $this->params->get('role_staff', 'staff'), $roles, true),
        ];
        $groups  = array_map('intval', $user->groups);
        $groups[] = (int) $this->params->get('group_client', 2);

        foreach ($managed as $gid => $wanted) {
            if ($gid === 8) {           // never touch Super Users
                continue;
            }

            $groups = $wanted ? array_merge($groups, [$gid]) : array_diff($groups, [$gid]);
        }

        $groups = array_values(array_unique($groups));
        $data   = ['groups' => $groups];

        if (!empty($c['name'])) {
            $data['name'] = (string) $c['name'];
        }

        if (!empty($c['email']) && filter_var($c['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN) && filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
            $data['email'] = (string) $c['email'];
        }

        sort($groups);
        $current = array_map('intval', $user->groups);
        sort($current);

        if ($groups !== $current || ($data['name'] ?? $user->name) !== $user->name || ($data['email'] ?? $user->email) !== $user->email) {
            $user->bind($data);

            if (!$user->save(true)) {
                Log::add("SSO: could not update account $userId: " . $user->getError(), Log::WARNING, 'ptsso');
            }

            Access::clearStatics();
        }
    }

    private function saveSub(int $userId, string $sub): void
    {
        $db  = $this->getDatabase();
        $key = self::PROFILE_KEY;
        $row = (object) ['user_id' => $userId, 'profile_key' => $key, 'profile_value' => json_encode($sub), 'ordering' => 0];
        $q   = $db->getQuery(true)
            ->delete($db->quoteName('#__user_profiles'))
            ->where($db->quoteName('user_id') . ' = :u')
            ->where($db->quoteName('profile_key') . ' = :k')
            ->bind(':u', $userId, ParameterType::INTEGER)
            ->bind(':k', $key);
        $db->setQuery($q)->execute();
        $db->insertObject('#__user_profiles', $row);
    }

    private function uniqueUsername(string $base): string
    {
        $base = strtolower(preg_replace('/[^A-Za-z0-9._-]/', '', $base)) ?: 'user';
        $db   = $this->getDatabase();
        $name = $base;

        for ($i = 2; $i < 1000; $i++) {
            $q = $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__users'))
                ->where($db->quoteName('username') . ' = :n')->bind(':n', $name);

            if (!(int) $db->setQuery($q)->loadResult()) {
                return $name;
            }

            $name = $base . $i;
        }

        return $base . bin2hex(random_bytes(3));
    }

    // ------------------------------------------------------------------ Joomla login (from plg_system_webauthn)

    private function loginUser(User $user): void
    {
        class_exists(Authentication::class, true);
        $app     = $this->getApplication();
        $isAdmin = $app->isClient('administrator');

        $response                = new AuthenticationResponse();
        $response->status        = Authentication::STATUS_SUCCESS;
        $response->username      = $user->username;
        $response->fullname      = $user->name;
        $response->email         = $user->email;
        $response->error_message = '';
        $response->language      = $user->getParam($isAdmin ? 'admin_language' : 'language');
        $response->type          = 'PamperinsSSO';

        $options = ['remember' => false, 'action' => $isAdmin ? 'core.login.admin' : 'core.login.site'];

        $dispatcher = $app->getDispatcher();
        PluginHelper::importPlugin('user', null, true, $dispatcher);

        $event   = new \Joomla\CMS\Event\User\LoginEvent('onUserLogin', ['options' => $options, 'subject' => (array) $response]);
        $result  = $dispatcher->dispatch('onUserLogin', $event);
        $results = !isset($result['result']) || \is_null($result['result']) ? [] : $result['result'];

        if (\in_array(false, $results, true) === false) {
            $app->getSession()->set('user', $user);
            $options['user']         = $user;
            $options['responseType'] = $response->type;
            $after = new \Joomla\CMS\Event\User\AfterLoginEvent('onUserAfterLogin', ['options' => $options, 'subject' => (array) $response]);
            $dispatcher->dispatch($after->getName(), $after);

            return;
        }

        $failure = new \Joomla\CMS\Event\User\LoginFailureEvent('onUserLoginFailure', ['options' => $options, 'subject' => (array) $response]);
        $dispatcher->dispatch('onUserLoginFailure', $failure);
        $this->fail('PLG_SYSTEM_PTSSO_ERR_TOKEN', 'a user plugin refused the login');
    }

    // ------------------------------------------------------------------ helpers

    private function enabledHere(): bool
    {
        $app = $this->getApplication();

        if (!$this->params->get('client_id') || !$this->params->get('issuer')) {
            return false;
        }

        return ($app->isClient('site') && $this->params->get('enable_site', 1))
            || ($app->isClient('administrator') && $this->params->get('enable_admin', 1));
    }

    /** index.php?ptsso=login starts the login; index.php?ptsso=callback is the redirect URI registered at the CRM. */
    private function endpointUrl(string $step, bool $absolute = false): string
    {
        $admin = $this->getApplication()->isClient('administrator');
        $path  = ($admin ? 'administrator/' : '') . 'index.php?ptsso=' . $step;

        return ($absolute ? rtrim(Uri::root(), '/') . '/' : Uri::root(true) . '/') . $path;
    }

    private function defaultReturn(): string
    {
        $app = $this->getApplication();

        if ($app->isClient('administrator')) {
            return rtrim(Uri::root(), '/') . '/administrator/index.php';
        }

        $return = (string) $app->getUserState('users.login.form.return', '');

        return ($return && Uri::isInternal($return)) ? $return : rtrim(Uri::root(), '/') . '/';
    }

    private function discovery(): array
    {
        static $cache = null;

        if ($cache) {
            return $cache;
        }

        $url = trim((string) $this->params->get('discovery_url')) ?: rtrim((string) $this->params->get('issuer'), '/') . '/.well-known/openid-configuration';
        $doc = $this->getJson($url);

        foreach (['issuer', 'authorization_endpoint', 'token_endpoint'] as $k) {
            if (empty($doc[$k]) || !$this->urlAllowed((string) $doc[$k])) {
                $this->fail('PLG_SYSTEM_PTSSO_ERR_PROVIDER', "discovery document missing or insecure '$k'");
            }
        }

        return $cache = $doc;
    }

    private function urlAllowed(string $url): bool
    {
        return str_starts_with($url, 'https://') || ($this->params->get('allow_http', 0) && str_starts_with($url, 'http://'));
    }

    private function getJson(string $url, string $bearer = ''): array
    {
        if (!$url || !$this->urlAllowed($url)) {
            return [];
        }

        try {
            $headers = ['Accept' => 'application/json'] + ($bearer ? ['Authorization' => 'Bearer ' . $bearer] : []);
            $res     = HttpFactory::getHttp()->get($url, $headers, 15);
            $data    = json_decode((string) $res->body, true);

            return $res->code === 200 && \is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            Log::add('SSO: GET ' . $url . ' failed: ' . $e->getMessage(), Log::WARNING, 'ptsso');

            return [];
        }
    }

    private function postForm(string $url, bool $basic, array $fields): array
    {
        if (!$this->urlAllowed($url)) {
            $this->fail('PLG_SYSTEM_PTSSO_ERR_PROVIDER', 'insecure token endpoint');
        }

        try {
            $headers = ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'];

            if ($basic) {   // client_secret_basic (RFC 6749 2.3.1): credentials in the header, not the body
                $headers['Authorization'] = 'Basic ' . base64_encode(rawurlencode($fields['client_id']) . ':' . rawurlencode($fields['client_secret']));
                unset($fields['client_secret']);
            }

            $res = HttpFactory::getHttp()->post($url, http_build_query($fields), $headers, 15);
            $data = json_decode((string) $res->body, true);

            if ($res->code !== 200 || !\is_array($data)) {
                $this->fail('PLG_SYSTEM_PTSSO_ERR_TOKEN', 'token endpoint HTTP ' . $res->code);
            }

            return $data;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->fail('PLG_SYSTEM_PTSSO_ERR_PROVIDER', 'token request failed: ' . $e->getMessage());
        }
    }

    private function users(): UserFactoryInterface
    {
        return \Joomla\CMS\Factory::getContainer()->get(UserFactoryInterface::class);
    }

    /** Show a friendly message on the login page and stop. Details go to the log only. */
    private function fail(string $langKey, string $detail = ''): never
    {
        if ($detail) {
            Log::add('SSO: ' . $detail, Log::WARNING, 'ptsso');
        }

        $app = $this->getApplication();
        $app->enqueueMessage(Text::_($langKey), 'warning');
        $app->redirect($app->isClient('administrator')
            ? rtrim(Uri::root(), '/') . '/administrator/index.php'
            : rtrim(Uri::root(), '/') . '/index.php?option=com_users&view=login');
    }
}
