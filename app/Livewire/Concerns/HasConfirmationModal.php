<?php

namespace App\Livewire\Concerns;

trait HasConfirmationModal
{
    public bool $showConfirmModal = false;
    public mixed $confirmTarget = null;
    public string $confirmAction = '';
    public string $confirmTitle = 'Konfirmasi Tindakan';
    public string $confirmDescription = 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
    public string $confirmButtonText = 'Ya, Lanjutkan';
    public string $confirmButtonVariant = 'primary'; // primary, danger, warning

    public function askConfirmation(
        mixed $target,
        string $action,
        string $title,
        string $description,
        string $buttonText = 'Ya, Lanjutkan',
        string $variant = 'primary',
        ?string $confirmButtonText = null,
        ?string $confirmVariant = null
    ): void {
        $this->confirmTarget = $target;
        $this->confirmAction = $action;
        $this->confirmTitle = $title;
        $this->confirmDescription = $description;
        $this->confirmButtonText = $confirmButtonText ?? $buttonText;
        $this->confirmButtonVariant = $confirmVariant ?? $variant;
        $this->showConfirmModal = true;
    }

    public function executeConfirmedAction(): void
    {
        $action = $this->confirmAction;
        $target = $this->confirmTarget;

        $this->showConfirmModal = false;

        if (method_exists($this, $action)) {
            if ($target !== null) {
                $this->$action($target);
            } else {
                $this->$action();
            }
        }
    }
}
