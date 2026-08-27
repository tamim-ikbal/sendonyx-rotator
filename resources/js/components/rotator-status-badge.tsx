import { Badge } from '@/components/ui/badge';
import type { DestinationStatus, RotatorStatus } from '@/types';

/**
 * Renders a rotator or destination status.
 *
 * Both enums carry the same two cases, and a paused destination means the same
 * thing to a reader as a paused rotator, so they share one badge.
 */
export default function RotatorStatusBadge({
    status,
}: {
    status: RotatorStatus | DestinationStatus;
}) {
    return (
        <Badge variant={status === 'active' ? 'secondary' : 'outline'}>
            <span
                className={
                    status === 'active'
                        ? 'size-1.5 rounded-full bg-emerald-500'
                        : 'size-1.5 rounded-full bg-muted-foreground'
                }
            />
            {status === 'active' ? 'Active' : 'Paused'}
        </Badge>
    );
}
