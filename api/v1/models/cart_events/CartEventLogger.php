<?php
declare(strict_types=1);

/**
 * CartEventLogger
 *
 * Centralised helper for writing rows into cart_events.
 * Logging MUST never crash the main operation — every write is wrapped in a
 * silent try/catch so a transient DB error during logging never propagates to
 * the caller.
 *
 * Usage (inside a service):
 *
 *   $this->logger->log($cart, 'item_added', [
 *       'related_item_id' => $itemId,
 *       'new_value'       => ['product_id' => 5, 'quantity' => 2],
 *   ]);
 *
 * The $cart array MUST contain at least:
 *   - 'id'        => cart primary-key
 *   - 'entity_id' => entity FK  (NEVER taken from the request)
 */
final class CartEventLogger
{
    private PdoCartEventsRepository $repo;
    private string $actorType = 'system';
    private ?int   $actorId   = null;

    public function __construct(PdoCartEventsRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Configure the actor for subsequent log() calls.
     * Call this once per request in the route file after resolving the session.
     *
     * @param string   $actorType 'user' | 'admin' | 'system'
     * @param int|null $actorId   The authenticated user/admin ID, or null
     */
    public function setActor(string $actorType, ?int $actorId): void
    {
        $this->actorType = in_array($actorType, ['user', 'admin', 'system'], true)
            ? $actorType
            : 'system';
        $this->actorId = $actorId;
    }

    /**
     * Write a cart event row.
     *
     * @param array  $cart      Must contain 'id' (cart PK) and 'entity_id'
     * @param string $eventType cart_created | item_added | item_removed |
     *                          quantity_updated | cart_updated | coupon_applied |
     *                          coupon_removed | cart_expired | cart_converted
     * @param array  $options   Optional overrides / extra data:
     *                          - actor_type      (overrides the instance default)
     *                          - actor_id        (overrides the instance default)
     *                          - related_item_id int|null
     *                          - old_value       mixed  (will be JSON-encoded)
     *                          - new_value       mixed  (will be JSON-encoded)
     *                          - note            string (max 255 chars)
     */
    public function log(array $cart, string $eventType, array $options = []): void
    {
        try {
            $actorType = isset($options['actor_type'])
                && in_array($options['actor_type'], ['user', 'admin', 'system'], true)
                    ? $options['actor_type']
                    : $this->actorType;

            $actorId = array_key_exists('actor_id', $options)
                ? ($options['actor_id'] !== null ? (int)$options['actor_id'] : null)
                : $this->actorId;

            $oldValue = array_key_exists('old_value', $options) && $options['old_value'] !== null
                ? json_encode($options['old_value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;

            $newValue = array_key_exists('new_value', $options) && $options['new_value'] !== null
                ? json_encode($options['new_value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;

            $this->repo->create([
                'entity_id'       => (int)$cart['entity_id'],
                'cart_id'         => (int)$cart['id'],
                'event_type'      => $eventType,
                'actor_type'      => $actorType,
                'actor_id'        => $actorId,
                'related_item_id' => isset($options['related_item_id'])
                    ? (int)$options['related_item_id']
                    : null,
                'old_value'       => $oldValue,
                'new_value'       => $newValue,
                'note'            => isset($options['note'])
                    ? substr((string)$options['note'], 0, 255)
                    : null,
            ]);
        } catch (\Throwable $e) {
            // Intentionally silent — logging must never abort the main operation.
        }
    }
}
