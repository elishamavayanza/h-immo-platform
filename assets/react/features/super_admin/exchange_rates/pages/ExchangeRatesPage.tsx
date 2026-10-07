import { useState, type FormEvent } from 'react';

import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { FormField } from '../../../../components/Forms/FormField';
import { Input } from '../../../../components/Forms/Input';
import { Button } from '../../../../components/UI/Button';
import { Card } from '../../../../components/UI/Card';
import { Modal } from '../../../../components/UI/Modal';
import { PlatformPageHeader } from '../../shared/PlatformPageHeader';
import { ExchangeRateSummary } from '../components/ExchangeRateSummary';
import { useExchangeRates } from '../hooks/useExchangeRates';
import type { ExchangeRateRecord } from '../types/exchangeRate.types';
import '../../../../../styles/pages/super_admin/exchange_rates/_exchange-rates.scss';

export function ExchangeRatesPage() {
    const { rates, updateRate } = useExchangeRates();
    const [isOpen, setOpen] = useState(false);
    const [rate, setRate] = useState('');
    const columns: DataTableColumn<ExchangeRateRecord>[] = [
        { key: 'effectiveAt', title: 'Date d’application', sortable: true },
        { key: 'pair', title: 'Paire de devises', sortable: true },
        { key: 'rate', title: 'Taux (CDF pour 1 USD)', sortable: true },
        { key: 'source', title: 'Source', sortable: true },
        { key: 'updatedBy', title: 'Modifié par', sortable: true },
    ];
    const handleUpdate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!rate || Number(rate) <= 0) return;
        updateRate(rate);
        setRate('');
        setOpen(false);
    };
    const current = rates[0];
    return <div className="sa-dashboard sa-management-page sa-exchange-page">
        <PlatformPageHeader title="Taux de change" description="Consultez le taux USD/CDF et l’historique de ses mises à jour." icon="revenue" action={<Button icon={<span aria-hidden="true">＋</span>} onClick={() => setOpen(true)}>Mettre à jour le taux</Button>} />
        <ExchangeRateSummary current={current} />
        <Card className="sa-management-table-card" padding="medium">
            <div className="sa-management-toolbar"><div><h2>Historique des taux</h2><p>Maquette locale : les modifications ne sont pas enregistrées dans l’API.</p></div></div>
            <DataTable columns={columns} data={rates} pageSize={8} initialSortKey="effectiveAt" />
        </Card>
        <Modal isOpen={isOpen} onClose={() => setOpen(false)} title="Mettre à jour le taux USD/CDF" size="small" footer={<><Button variant="outline" onClick={() => setOpen(false)}>Annuler</Button><Button type="submit" form="exchange-rate-form">Enregistrer</Button></>}>
            <form id="exchange-rate-form" className="sa-management-form" onSubmit={handleUpdate}>
                <p className="sa-management-form__hint">Cette valeur sera ajoutée à l’historique local de la maquette.</p>
                <FormField label="1 USD équivaut à (CDF)" htmlFor="exchange-rate" required><Input id="exchange-rate" type="number" min="0.01" step="0.01" value={rate} onChange={(event) => setRate(event.target.value)} placeholder="Ex. 2850,00" required fullWidth /></FormField>
            </form>
        </Modal>
    </div>;
}
