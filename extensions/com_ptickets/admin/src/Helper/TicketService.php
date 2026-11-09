<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Administrator\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;

\defined('_JEXEC') or die;

/**
 * Creates tickets and replies, sends the e-mails and fires plugin events.
 *
 * Events (plugin group "ptickets") — use these to copy tickets into RSUP later:
 *   onPticketsAfterCreate  ['ticket' => object]
 *   onPticketsAfterReply   ['ticket' => object, 'reply' => object]
 *   onPticketsAfterUpdate  ['ticket' => object]   (status/priority/assignee changed in admin)
 */
final class TicketService
{
    private static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    public static function params()
    {
        return ComponentHelper::getParams('com_ptickets');
    }

    /**
     * @param  array  $data  type, subject, description, phone, contact_method, priority, details(array)
     */
    public static function create(User $user, array $data): object
    {
        SupportTypes::loadSiteLanguage(); // e-mail wording lives in the site language file
        $db  = self::db();
        $now = Factory::getDate()->toSql();

        $ticket = (object) [
            'ref'            => '',
            'user_id'        => (int) $user->id,
            'type'           => (string) $data['type'],
            'subject'        => mb_substr(trim((string) $data['subject']), 0, 255),
            'description'    => trim((string) $data['description']),
            'details'        => json_encode($data['details'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'phone'          => mb_substr(trim((string) ($data['phone'] ?? '')), 0, 40),
            'contact_method' => \in_array($data['contact_method'] ?? '', ['email', 'phone', 'text'], true) ? $data['contact_method'] : 'email',
            'priority'       => \in_array($data['priority'] ?? '', SupportTypes::PRIORITIES, true) ? $data['priority'] : 'normal',
            'status'         => 'new',
            'assigned_to'    => 0,
            'created'        => $now,
        ];

        $db->insertObject('#__ptickets_tickets', $ticket, 'id');

        $ticket->ref = self::params()->get('ref_prefix', 'PT-') . str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT);
        $query       = $db->getQuery(true)
            ->update($db->quoteName('#__ptickets_tickets'))
            ->set($db->quoteName('ref') . ' = :ref')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':ref', $ticket->ref)
            ->bind(':id', $ticket->id, ParameterType::INTEGER);
        $db->setQuery($query)->execute();

        self::dispatch('onPticketsAfterCreate', ['ticket' => $ticket]);
        self::notifyStaffNew($ticket, $user);
        self::notifyCustomer($ticket, $user, Text::sprintf('COM_PTICKETS_MAIL_CUSTOMER_NEW_SUBJECT', $ticket->ref), Text::sprintf(
            'COM_PTICKETS_MAIL_CUSTOMER_NEW_BODY',
            $user->name,
            $ticket->ref,
            $ticket->subject,
            self::ticketUrl((int) $ticket->id)
        ));

        return $ticket;
    }

    public static function addReply(object $ticket, User $author, string $body, bool $internal, bool $fromStaff): object
    {
        SupportTypes::loadSiteLanguage(); // also needed when staff reply from the admin
        $db    = self::db();
        $reply = (object) [
            'ticket_id' => (int) $ticket->id,
            'user_id'   => (int) $author->id,
            'body'      => trim($body),
            'internal'  => $internal ? 1 : 0,
            'created'   => Factory::getDate()->toSql(),
        ];
        $db->insertObject('#__ptickets_replies', $reply, 'id');

        // A customer reply re-opens a ticket that was waiting on them or marked resolved
        if (!$fromStaff && \in_array($ticket->status, ['waiting', 'resolved'], true)) {
            self::setFields((int) $ticket->id, ['status' => 'open']);
            $ticket->status = 'open';
        } else {
            self::setFields((int) $ticket->id, []);
        }

        self::dispatch('onPticketsAfterReply', ['ticket' => $ticket, 'reply' => $reply]);

        if ($fromStaff && !$internal) {
            $customer = Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById((int) $ticket->user_id);
            self::notifyCustomer($ticket, $customer, Text::sprintf('COM_PTICKETS_MAIL_CUSTOMER_REPLY_SUBJECT', $ticket->ref), Text::sprintf(
                'COM_PTICKETS_MAIL_CUSTOMER_REPLY_BODY',
                $customer->name,
                $ticket->ref,
                $reply->body,
                self::ticketUrl((int) $ticket->id)
            ));
        } elseif (!$fromStaff) {
            self::mail(self::staffAddress(), Text::sprintf('COM_PTICKETS_MAIL_STAFF_REPLY_SUBJECT', $ticket->ref, $ticket->subject), Text::sprintf(
                'COM_PTICKETS_MAIL_STAFF_REPLY_BODY',
                $author->name,
                $ticket->ref,
                $reply->body,
                self::adminUrl((int) $ticket->id)
            ));
        }

        return $reply;
    }

    /** Update columns on a ticket and touch `modified`. */
    public static function setFields(int $id, array $fields): void
    {
        $db             = self::db();
        $fields['modified'] = Factory::getDate()->toSql();
        $row            = (object) (['id' => $id] + $fields);
        $db->updateObject('#__ptickets_tickets', $row, 'id');
    }

    public static function load(int $id): ?object
    {
        $db    = self::db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ptickets_tickets'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /** @return object[] replies with author name; internal notes only when $includeInternal */
    public static function replies(int $ticketId, bool $includeInternal): array
    {
        $db    = self::db();
        $query = $db->getQuery(true)
            ->select(['r.*', $db->quoteName('u.name', 'author_name')])
            ->from($db->quoteName('#__ptickets_replies', 'r'))
            ->join('LEFT', $db->quoteName('#__users', 'u'), 'u.id = r.user_id')
            ->where($db->quoteName('r.ticket_id') . ' = :id')
            ->bind(':id', $ticketId, ParameterType::INTEGER)
            ->order($db->quoteName('r.created') . ' ASC, r.id ASC');

        if (!$includeInternal) {
            $query->where($db->quoteName('r.internal') . ' = 0');
        }

        return $db->setQuery($query)->loadObjectList();
    }

    public static function dispatch(string $name, array $args): void
    {
        try {
            PluginHelper::importPlugin('ptickets');
            Factory::getApplication()->getDispatcher()->dispatch($name, new Event($name, $args));
        } catch (\Throwable $e) {
            // A broken plugin must never lose a customer's ticket
        }
    }

    private static function notifyStaffNew(object $ticket, User $user): void
    {
        $rows = '';

        foreach (SupportTypes::describeDetails($ticket->type, $ticket->details) as [$label, $value]) {
            $rows .= $label . ': ' . $value . "\n";
        }

        self::mail(self::staffAddress(), Text::sprintf('COM_PTICKETS_MAIL_STAFF_NEW_SUBJECT', $ticket->ref, SupportTypes::title($ticket->type), $ticket->subject), Text::sprintf(
            'COM_PTICKETS_MAIL_STAFF_NEW_BODY',
            $ticket->ref,
            SupportTypes::title($ticket->type),
            SupportTypes::priorityLabel($ticket->priority),
            $user->name,
            $user->email,
            $ticket->phone ?: '-',
            $ticket->contact_method,
            $ticket->subject,
            $ticket->description,
            $rows,
            self::adminUrl((int) $ticket->id)
        ));
    }

    private static function notifyCustomer(object $ticket, User $user, string $subject, string $body): void
    {
        if ((int) self::params()->get('customer_email', 1) === 1 && $user->email) {
            self::mail($user->email, $subject, $body);
        }
    }

    private static function staffAddress(): string
    {
        return self::params()->get('notify_email') ?: Factory::getApplication()->get('mailfrom');
    }

    private static function mail(string $to, string $subject, string $body): void
    {
        if (!$to) {
            return;
        }

        try {
            $app    = Factory::getApplication();
            $mailer = Factory::getContainer()->get(\Joomla\CMS\Mail\MailerFactoryInterface::class)->createMailer();
            $mailer->setSender([$app->get('mailfrom'), $app->get('fromname')]);
            $mailer->addRecipient($to);
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->send();
        } catch (\Throwable $e) {
            // Mail problems are logged by Joomla; the ticket is already saved.
        }
    }

    private static function ticketUrl(int $id): string
    {
        return rtrim(Uri::root(), '/') . '/index.php?option=com_ptickets&view=ticket&id=' . $id;
    }

    private static function adminUrl(int $id): string
    {
        return rtrim(Uri::root(), '/') . '/administrator/index.php?option=com_ptickets&task=ticket.edit&id=' . $id;
    }
}
