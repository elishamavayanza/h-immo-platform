import { InlineSummary } from '../../../shared/components/InlineSummary';
import type { OrganizationRow } from '../types/organization.types';

export function OrganizationsStats({ organizations }: { organizations: OrganizationRow[] }) {
    return <InlineSummary items={[
        { label: 'Organisations', value: organizations.length },
        { label: 'Actives', value: organizations.filter((item) => item.status === 'active').length },
        { label: 'Suspendues', value: organizations.filter((item) => item.status === 'suspended').length },
        { label: 'Inactives', value: organizations.filter((item) => item.status === 'inactive').length },
    ]} />;
}
