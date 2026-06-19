<x-filament-panels::page>
    <div class="mb-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Upload a CSV or XLSX file with guest details. Review the imported contacts below and click <strong>Register</strong> to create their registration with QR code, email, and SMS notifications.
        </p>
    </div>
    {{ $this->table }}
</x-filament-panels::page>
