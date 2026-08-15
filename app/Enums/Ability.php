<?php

namespace App\Enums;

/**
 * Every action the app authorises. Screens and endpoints name one of these
 * instead of listing roles, so adding a role never means editing 11 resources.
 */
final class Ability
{
    public const EventsView = 'events.view';

    public const EventsManage = 'events.manage';

    public const GuestsView = 'guests.view';

    public const GuestsEdit = 'guests.edit';

    public const GuestsRegister = 'guests.register';

    public const GuestsApprove = 'guests.approve';

    public const LabelsPrint = 'labels.print';

    public const TicketsView = 'tickets.view';

    public const Scan = 'scan';

    public const CommunicationsView = 'communications.view';

    public const CommunicationsSend = 'communications.send';

    public const ImportsManage = 'imports.manage';

    public const PaymentsView = 'payments.view';

    public const PaymentsManage = 'payments.manage';

    public const ReportsView = 'reports.view';

    public const UsersManage = 'users.manage';

    public const SettingsManage = 'settings.manage';

    /** @return list<string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
