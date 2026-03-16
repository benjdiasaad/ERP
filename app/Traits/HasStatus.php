<?php

declare(strict_types=1);

namespace App\Traits;

use InvalidArgumentException;

/**
 * HasStatus — generic status transition validation helper.
 *
 * Usage in a model:
 *
 *   use HasStatus;
 *
 *   protected array $statusTransitions = [
 *       'draft'  => ['sent', 'cancelled'],
 *       'sent'   => ['accepted', 'rejected'],
 *       'accepted' => ['converted'],
 *   ];
 *
 * The model MUST define $statusTransitions as a protected property.
 * The model MUST have a `status` column (or override getStatusColumn()).
 */
trait HasStatus
{
    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    protected static function bootHasStatus(): void
    {
        static::updating(function (self $model): void {
            $column = $model->getStatusColumn();

            if (!$model->isDirty($column)) {
                return;
            }

            $from = $model->getOriginal($column);
            $to   = $model->getAttribute($column);

            if ($from !== null && !$model->canTransitionTo($to)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid status transition on %s: "%s" → "%s". Allowed: [%s].',
                        class_basename($model),
                        $from,
                        $to,
                        implode(', ', $model->getAllowedTransitions())
                    )
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Check whether transitioning to $status is allowed from the current status.
     */
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->getAllowedTransitions(), true);
    }

    /**
     * Transition to $status, persisting the change.
     *
     * @throws InvalidArgumentException if the transition is not allowed.
     */
    public function transitionTo(string $status): static
    {
        if (!$this->canTransitionTo($status)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot transition %s from "%s" to "%s". Allowed: [%s].',
                    class_basename($this),
                    $this->getCurrentStatus(),
                    $status,
                    implode(', ', $this->getAllowedTransitions())
                )
            );
        }

        $this->setAttribute($this->getStatusColumn(), $status);
        $this->save();

        return $this;
    }

    /**
     * Return the list of statuses reachable from the current status.
     */
    public function getAllowedTransitions(): array
    {
        $current = $this->getCurrentStatus();
        $map     = $this->getStatusTransitions();

        return $map[$current] ?? [];
    }

    /**
     * Return the current status value.
     */
    public function getCurrentStatus(): ?string
    {
        return $this->getAttribute($this->getStatusColumn());
    }

    /**
     * Check whether the model is in a given status.
     */
    public function isStatus(string $status): bool
    {
        return $this->getCurrentStatus() === $status;
    }

    /**
     * Check whether the model is in any of the given statuses.
     */
    public function isStatusIn(array $statuses): bool
    {
        return in_array($this->getCurrentStatus(), $statuses, true);
    }

    // -------------------------------------------------------------------------
    // Overridable helpers
    // -------------------------------------------------------------------------

    /**
     * The name of the status column. Override in the model if needed.
     */
    public function getStatusColumn(): string
    {
        return 'status';
    }

    /**
     * Return the transition map. Models define $statusTransitions property;
     * override this method for dynamic maps.
     *
     * @return array<string, string[]>
     */
    public function getStatusTransitions(): array
    {
        return $this->statusTransitions ?? [];
    }
}
