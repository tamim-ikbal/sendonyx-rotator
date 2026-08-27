export type RotatorStatus = 'active' | 'paused';

export type DestinationStatus = 'active' | 'paused';

export type Rotator = {
    uuid: string;
    name: string;
    slug: string;
    status: RotatorStatus;
    default_destination_url: string | null;
    created_at: string | null;
};

export type RotatorListItem = Rotator & {
    destinations_count: number;
    views_count: number;
};

export type RotatorDestination = {
    uuid: string;
    url: string;
    weight: number;
    status: DestinationStatus;
    views_count: number;
    created_at: string | null;
};
