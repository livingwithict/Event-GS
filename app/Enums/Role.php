<?php

namespace App\Enums;

/**
 * The seven roles, and the one place that says what each may do.
 *
 * Abilities are checked through the Gate (`$user->can('guests.edit')`), so
 * screens and endpoints ask about the action, never about the role name.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case EventAdmin = 'event_admin';
    case RegistrationStaff = 'registration_staff';
    case ScannerStaff = 'scanner_staff';
    case Finance = 'finance';
    case Viewer = 'viewer';
    case InvitationStaff = 'invitation_staff';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::EventAdmin => 'Event Admin',
            self::RegistrationStaff => 'Registration Staff',
            self::ScannerStaff => 'Scanner Staff',
            self::Finance => 'Finance / Accounts',
            self::Viewer => 'Viewer (read only)',
            self::InvitationStaff => 'Invitation Staff',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Full control of every event, plus user management and system settings.',
            self::EventAdmin => 'Full control of the events assigned to them. Cannot see other events or manage users.',
            self::RegistrationStaff => 'Runs the desk: registers walk-ins, corrects guest details, prints labels and scans at the door.',
            self::ScannerStaff => 'Scans guests in at the entrance, lunch and dinner. Sees nothing else.',
            self::Finance => 'Payments, invoices and revenue reports across their events.',
            self::Viewer => 'Reads guests, attendance and reports. Changes nothing, sees no payments.',
            self::InvitationStaff => 'Manages guest records and sends invitations/communications. No scanning, payments or settings access.',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::EventAdmin => 'warning',
            self::RegistrationStaff => 'info',
            self::ScannerStaff => 'success',
            self::Finance => 'primary',
            self::Viewer => 'gray',
            self::InvitationStaff => 'purple',
        };
    }

    /** Roles limited to the events assigned to them on the user record. */
    public function isEventScoped(): bool
    {
        return $this !== self::SuperAdmin;
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return match ($this) {
            self::SuperAdmin => Ability::all(),

            self::EventAdmin => [
                Ability::EventsView, Ability::EventsManage,
                Ability::GuestsView, Ability::GuestsEdit, Ability::GuestsRegister, Ability::GuestsApprove,
                Ability::LabelsPrint, Ability::DeliveryManage, Ability::TicketsView, Ability::Scan,
                Ability::CommunicationsView, Ability::CommunicationsSend,
                Ability::ImportsManage, Ability::ReportsView,
                Ability::PaymentsView,
                Ability::SettingsManage,
            ],

            self::RegistrationStaff => [
                Ability::EventsView,
                Ability::GuestsView, Ability::GuestsEdit, Ability::GuestsRegister,
                Ability::LabelsPrint, Ability::DeliveryManage, Ability::TicketsView, Ability::Scan,
            ],

            // Deliberately narrow: the Scan Station shows the guest it just
            // scanned, so door staff never need to browse the guest list.
            // LabelsPrint is included so entrance staff can print name tags
            // at check-in without registration-staff access.
            self::ScannerStaff => [
                Ability::Scan, Ability::LabelsPrint,
            ],

            self::Finance => [
                Ability::EventsView,
                Ability::GuestsView,
                Ability::PaymentsView, Ability::PaymentsManage,
                Ability::ReportsView,
            ],

            self::Viewer => [
                Ability::EventsView,
                Ability::GuestsView,
                Ability::ReportsView,
            ],

            self::InvitationStaff => [
                Ability::EventsView,
                Ability::GuestsView, Ability::GuestsEdit, Ability::GuestsRegister,
                Ability::CommunicationsView, Ability::CommunicationsSend,
                Ability::LabelsPrint, Ability::TicketsView,
            ],
        };
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }

    /** @return array<string, string> value => label, for form dropdowns */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $role) => $carry + [$role->value => $role->label()],
            [],
        );
    }
}
