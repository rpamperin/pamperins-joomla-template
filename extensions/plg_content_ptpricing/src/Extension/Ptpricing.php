<?php

/**
 * Pamperins Pricing: show the price list from the office backend (rsup) on the website.
 *
 * In an article, the hand-typed price tables sit between {ptpricing} and {/ptpricing}.
 * While the office price list (a public JSON feed, see docs/pricing-feed.md) can be read, that block is replaced
 * with tables built from it. Otherwise the hand-typed tables are shown, so the page never goes blank.
 *
 * The feed is read at most every N minutes (setting); the last good copy is kept in administrator/cache/plg_content_ptpricing/.
 * Problems reading it are written to administrator/logs/ptpricing.php.
 *
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Plugin\Content\Ptpricing\Extension;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

final class Ptpricing extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /** {ptpricing}fallback{/ptpricing}, allowing the editor's <p> wrappers around the tags */
    private const BLOCK = '#(?:<p>\s*)?\{ptpricing\}(?:\s*</p>)?(.*?)(?:<p>\s*)?\{/ptpricing\}(?:\s*</p>)?#s';

    private const MAX_ITEMS = 60;

    private ?array $feed = null;

    private bool $loaded = false;

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    public function onContentPrepare(Event $event): void
    {
        $item = method_exists($event, 'getItem') ? $event->getItem() : (array_values($event->getArguments())[1] ?? null);

        if (!\is_object($item) || empty($item->text) || !str_contains($item->text, '{ptpricing}')) {
            return;
        }

        $item->text = preg_replace_callback(self::BLOCK, function (array $m): string {
            $feed = $this->feed();

            return $feed ? $this->render($feed) : $m[1];
        }, $item->text);
    }

    // ------------------------------------------------------------------ feed

    private function feed(): ?array
    {
        if ($this->loaded) {
            return $this->feed;
        }

        $this->loaded = true;
        $file   = JPATH_CACHE . '/plg_content_ptpricing/feed.json';
        $cached = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $maxAge = max(1, (int) $this->params->get('refresh', 1)) * 60;

        if (\is_array($cached) && time() - (int) ($cached['fetched'] ?? 0) < $maxAge) {
            return $this->feed = $cached['feed'];
        }

        $fresh = $this->fetch();

        if ($fresh !== null) {
            if (!is_dir(\dirname($file))) {
                @mkdir(\dirname($file), 0755, true);
            }

            $tmp = $file . '.' . bin2hex(random_bytes(4));

            if (@file_put_contents($tmp, json_encode(['fetched' => time(), 'feed' => $fresh])) !== false) {
                @rename($tmp, $file);
            }

            return $this->feed = $fresh;
        }

        // Office server unreachable: keep showing the last good price list (however old), else the hand-typed one
        return $this->feed = \is_array($cached) ? $cached['feed'] : null;
    }

    private function fetch(): ?array
    {
        static $logger = false;

        if (!$logger) {
            Log::addLogger(['text_file' => 'ptpricing.php'], Log::ALL, ['ptpricing']);
            $logger = true;
        }

        $url = trim((string) $this->params->get('feed_url', ''));

        if ($url === '' || !preg_match($this->params->get('allow_http', 0) ? '#^https?://#i' : '#^https://#i', $url)) {
            return null;
        }

        try {
            $res = HttpFactory::getHttp()->get($url, ['Accept' => 'application/json'], 5);
        } catch (\Throwable $e) {
            Log::add('Pricing feed: ' . $e->getMessage(), Log::WARNING, 'ptpricing');

            return null;
        }

        if ($res->code !== 200) {
            Log::add('Pricing feed: HTTP ' . $res->code, Log::WARNING, 'ptpricing');

            return null;
        }

        $data = json_decode((string) $res->body, true);
        $feed = \is_array($data) ? $this->clean($data) : null;

        if ($feed === null) {
            Log::add('Pricing feed: not a valid version 1 price list', Log::WARNING, 'ptpricing');
        }

        return $feed;
    }

    /** Keep only the fields we know, with the types we expect. Returns null for anything unusable. */
    private function clean(array $d): ?array
    {
        if ((int) ($d['version'] ?? 0) !== 1 || (!\is_array($d['plans'] ?? null) && !\is_array($d['services'] ?? null))) {
            return null;
        }

        $str = static fn ($v, int $max = 200): string => \is_scalar($v) ? mb_substr(trim((string) $v), 0, $max) : '';
        $out = [
            'updated_at' => $str($d['updated_at'] ?? '', 40),
            'currency'   => strtoupper($str($d['currency'] ?? 'USD', 3)) ?: 'USD',
            'price_note' => $str($d['price_note'] ?? '', 300),
            'plans'      => [],
            'services'   => [],
        ];

        foreach (\array_slice((array) ($d['plans'] ?? []), 0, self::MAX_ITEMS) as $p) {
            if (!\is_array($p) || $str($p['name'] ?? '') === '') {
                continue;
            }

            $features = [];

            foreach (\array_slice((array) ($p['features'] ?? []), 0, self::MAX_ITEMS) as $f) {
                if (\is_array($f) && $str($f['label'] ?? '') !== '') {
                    $v          = $f['value'] ?? '';
                    $features[] = ['label' => $str($f['label']), 'value' => \is_bool($v) ? $v : $str($v)];
                }
            }

            $out['plans'][] = [
                'name'        => $str($p['name'], 80),
                'price_cents' => is_numeric($p['price_cents'] ?? null) ? max(0, (int) $p['price_cents']) : null,
                'period'      => \in_array($p['period'] ?? '', ['month', 'year', 'once'], true) ? $p['period'] : 'month',
                'price_text'  => $str($p['price_text'] ?? '', 80),
                'featured'    => !empty($p['featured']),
                'summary'     => $str($p['summary'] ?? ''),
                'notes'       => $str($p['notes'] ?? '', 300),
                'features'    => $features,
            ];
        }

        foreach (\array_slice((array) ($d['services'] ?? []), 0, self::MAX_ITEMS * 3) as $s) {
            if (!\is_array($s) || $str($s['name'] ?? '') === '') {
                continue;
            }

            $out['services'][] = [
                'name'        => $str($s['name'], 120),
                'price_cents' => is_numeric($s['price_cents'] ?? null) ? max(0, (int) $s['price_cents']) : null,
                'unit'        => \in_array($s['unit'] ?? '', ['flat', 'hour', 'session', 'device', 'month'], true) ? $s['unit'] : 'flat',
                'price_text'  => $str($s['price_text'] ?? '', 80),
                'notes'       => $str($s['notes'] ?? ''),
            ];
        }

        return ($out['plans'] || $out['services']) ? $out : null;
    }

    // ------------------------------------------------------------------ HTML

    private function render(array $feed): string
    {
        $e    = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $html = '<div class="pt-pricing-live">';

        if ($feed['plans']) {
            $plans  = $feed['plans'];
            $labels = [];

            foreach ($plans as $p) {
                foreach ($p['features'] as $f) {
                    $labels[$f['label']] = true;
                }
            }

            $html .= '<table class="pt-pricing pt-pricing--live"><thead><tr><th scope="col">' . Text::_('PLG_CONTENT_PTPRICING_PLAN') . '</th>';

            foreach ($plans as $p) {
                $html .= '<th scope="col"' . ($p['featured'] ? ' class="featured"' : '') . '>'
                    . $e($p['name']) . '</th>';
            }

            $html .= '</tr></thead><tbody><tr><th scope="row">' . Text::_('PLG_CONTENT_PTPRICING_PRICE') . '</th>';

            foreach ($plans as $p) {
                $html .= '<td' . ($p['featured'] ? ' class="featured"' : '') . '><span class="price">' . $this->price($p, $feed['currency'], $p['period'], $e) . '</span></td>';
            }

            $html .= '</tr>';

            // Optional text rows under the price: the plan's summary, then its notes (only when some plan has one)
            foreach (['summary', 'notes'] as $key) {
                if (!array_filter(array_column($plans, $key))) {
                    continue;
                }

                $html .= '<tr class="pt-pricing__' . $key . '"><th scope="row"></th>';

                foreach ($plans as $p) {
                    $html .= '<td' . ($p['featured'] ? ' class="featured"' : '') . '>' . $e($p[$key]) . '</td>';
                }

                $html .= '</tr>';
            }

            foreach (array_keys($labels) as $label) {
                $html .= '<tr><th scope="row">' . $e((string) $label) . '</th>';

                foreach ($plans as $p) {
                    $value = null;

                    foreach ($p['features'] as $f) {
                        if ($f['label'] === (string) $label) {
                            $value = $f['value'];
                            break;
                        }
                    }

                    $cell = match (true) {
                        $value === true           => '<span aria-hidden="true">✓</span><span class="visually-hidden">' . Text::_('PLG_CONTENT_PTPRICING_INCLUDED') . '</span>',
                        $value === false, $value === null, $value === ''
                                                  => '<span aria-hidden="true">—</span><span class="visually-hidden">' . Text::_('PLG_CONTENT_PTPRICING_NOT_INCLUDED') . '</span>',
                        default                   => $e($value),
                    };
                    $html .= '<td' . ($p['featured'] ? ' class="featured"' : '') . '>' . $cell . '</td>';
                }

                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        if ($feed['services']) {
            $heading = trim((string) $this->params->get('services_heading', 'One-time services'));
            $html   .= ($heading !== '' ? '<h2>' . $e($heading) . '</h2>' : '')
                . '<table class="pt-services-table"><thead><tr><th scope="col">' . Text::_('PLG_CONTENT_PTPRICING_SERVICE') . '</th><th scope="col">'
                . Text::_('PLG_CONTENT_PTPRICING_PRICE') . '</th><th scope="col">' . Text::_('PLG_CONTENT_PTPRICING_NOTES') . '</th></tr></thead><tbody>';

            foreach ($feed['services'] as $s) {
                $html .= '<tr><td>' . $e($s['name']) . '</td><td>' . $this->price($s, $feed['currency'], $s['unit'], $e) . '</td><td>' . $e($s['notes']) . '</td></tr>';
            }

            $html .= '</tbody></table>';
        }

        if ($feed['price_note'] !== '') {
            $html .= '<p class="pt-note">' . $e($feed['price_note']) . '</p>';
        }

        if ($this->params->get('show_updated', 1) && ($ts = strtotime($feed['updated_at'])) !== false) {
            $html .= '<p class="pt-pricing-updated">' . Text::sprintf('PLG_CONTENT_PTPRICING_UPDATED', HTMLHelper::_('date', date('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC3'))) . '</p>';
        }

        return $html . '</div>';
    }

    /** "$29<small>/mo</small>", "$45", "$90<small>/hr</small>", or the item's own price_text */
    private function price(array $item, string $currency, string $per, callable $e): string
    {
        if ($item['price_text'] !== '') {
            return $e($item['price_text']);
        }

        if ($item['price_cents'] === null) {
            return '';
        }

        $cents  = $item['price_cents'];
        $amount = number_format($cents / 100, $cents % 100 ? 2 : 0);
        $amount = $currency === 'USD' ? '$' . $amount : $e($currency) . ' ' . $amount;
        $suffix = [
            'month'  => 'PLG_CONTENT_PTPRICING_PER_MONTH',
            'year'   => 'PLG_CONTENT_PTPRICING_PER_YEAR',
            'hour'   => 'PLG_CONTENT_PTPRICING_PER_HOUR',
            'device' => 'PLG_CONTENT_PTPRICING_PER_DEVICE',
        ][$per] ?? '';

        return $amount . ($suffix ? '<small>' . Text::_($suffix) . '</small>' : '');
    }
}
