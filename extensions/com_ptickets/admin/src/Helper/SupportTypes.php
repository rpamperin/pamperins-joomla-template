<?php

/**
 * @package     Pamperins.Ptickets
 * @copyright   (C) 2026 Pamperins Tech
 * @license     GNU General Public License version 2 or later
 */

namespace Pamperins\Component\Ptickets\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * The request types, statuses and priorities, in one place.
 * To add a request type: add it to TYPES, create site/forms/type_<key>.xml,
 * and add its COM_PTICKETS_TYPE_<KEY>... strings to the site language file.
 */
final class SupportTypes
{
    /** key => group ('support' = something is broken, 'project' = new work) */
    public const TYPES = [
        'remote'     => 'support',
        'onsite'     => 'support',
        'network'    => 'support',
        'website'    => 'project',
        'software'   => 'project',
        'plc'        => 'project',
        'ai'         => 'project',
        'automation' => 'project',
    ];

    public const STATUSES = ['new', 'open', 'waiting', 'scheduled', 'resolved', 'closed'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public static function isValid(?string $type): bool
    {
        return $type !== null && isset(self::TYPES[$type]);
    }

    public static function title(string $type): string
    {
        self::loadSiteLanguage();

        return Text::_('COM_PTICKETS_TYPE_' . strtoupper($type));
    }

    public static function description(string $type): string
    {
        self::loadSiteLanguage();

        return Text::_('COM_PTICKETS_TYPE_' . strtoupper($type) . '_DESC');
    }

    /** @return array<string,string[]>  group => [type keys] */
    public static function grouped(): array
    {
        $out = [];

        foreach (self::TYPES as $key => $group) {
            $out[$group][] = $key;
        }

        return $out;
    }

    public static function formFile(string $type): string
    {
        return JPATH_SITE . '/components/com_ptickets/forms/type_' . $type . '.xml';
    }

    public static function statusLabel(string $status): string
    {
        return Text::_('COM_PTICKETS_STATUS_' . strtoupper($status));
    }

    public static function priorityLabel(string $priority): string
    {
        return Text::_('COM_PTICKETS_PRIORITY_' . strtoupper($priority));
    }

    /** Bootstrap badge colour for a status */
    public static function statusBadge(string $status): string
    {
        return [
            'new'       => 'bg-info text-dark',
            'open'      => 'bg-primary',
            'waiting'   => 'bg-warning text-dark',
            'scheduled' => 'bg-secondary',
            'resolved'  => 'bg-success',
            'closed'    => 'bg-dark',
        ][$status] ?? 'bg-secondary';
    }

    public static function priorityBadge(string $priority): string
    {
        return [
            'low'    => 'bg-light text-dark',
            'normal' => 'bg-secondary',
            'high'   => 'bg-warning text-dark',
            'urgent' => 'bg-danger',
        ][$priority] ?? 'bg-secondary';
    }

    /**
     * Turn the saved answers (JSON) into [label, value] rows, using the labels
     * and option texts from the type's form file.
     *
     * @return  array<int, array{0:string,1:string}>
     */
    public static function describeDetails(string $type, $details): array
    {
        self::loadSiteLanguage();

        $values = \is_array($details) ? $details : (json_decode((string) $details, true) ?: []);
        $rows   = [];
        $file   = self::formFile($type);

        if (!self::isValid($type) || !is_file($file)) {
            foreach ($values as $k => $v) {
                $rows[] = [(string) $k, \is_array($v) ? implode(', ', $v) : (string) $v];
            }

            return $rows;
        }

        $xml = simplexml_load_file($file);

        foreach ($xml->xpath('//field') as $field) {
            $name = (string) $field['name'];

            if (!\array_key_exists($name, $values) || $values[$name] === '' || $values[$name] === []) {
                continue;
            }

            $options = [];

            foreach ($field->option as $opt) {
                $options[(string) $opt['value']] = Text::_(trim((string) $opt));
            }

            $value = (array) $values[$name];
            $value = array_map(static fn ($v) => $options[$v] ?? (string) $v, $value);
            $rows[] = [Text::_((string) $field['label']), implode(', ', $value)];
        }

        return $rows;
    }

    public static function loadSiteLanguage(): void
    {
        static $done = false;

        if (!$done) {
            $lang = Factory::getApplication()->getLanguage();
            $lang->load('com_ptickets', JPATH_SITE) || $lang->load('com_ptickets', JPATH_SITE . '/components/com_ptickets');
            $done = true;
        }
    }
}
