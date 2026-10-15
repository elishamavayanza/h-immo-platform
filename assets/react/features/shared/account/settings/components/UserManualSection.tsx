import { useEffect, useRef, useState } from 'react';
import { Button } from '../../../../../components/UI/Button';
import { Card } from '../../../../../components/UI/Card';
import { ApiError } from '../../../../../../services/api/api.types';
import { userManualService } from '../services/userManualService';
import type { UserManual } from '../types/userManual.types';

export function UserManualSection() {
    const [manual, setManual] = useState<UserManual | null>(null);
    const [pages, setPages] = useState<Array<{ page: number; url: string }>>([]);
    const pageUrlsRef = useRef<string[]>([]);
    const stageRef = useRef<HTMLDivElement>(null);
    const [page, setPage] = useState(1);
    const [zoom, setZoom] = useState(100);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let active = true;
        void userManualService.getCurrent()
            .then((value) => { if (active) setManual(value); })
            .catch((cause: unknown) => {
                if (active) setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les informations du manuel.');
            })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, []);

    useEffect(() => () => { pageUrlsRef.current.forEach((url) => URL.revokeObjectURL(url)); }, []);

    const openReader = async (targetPage = 1) => {
        setBusy(true);
        setError(null);
        try {
            if (pages.length === 0) {
                if (!manual) return;
                const blobs = await Promise.all(Array.from({ length: manual.pageCount }, (_, index) => userManualService.getPage(index + 1)));
                const nextPages = blobs.map((blob, index) => ({ page: index + 1, url: URL.createObjectURL(new Blob([blob], { type: 'image/jpeg' })) }));
                pageUrlsRef.current.forEach((url) => URL.revokeObjectURL(url));
                pageUrlsRef.current = nextPages.map(({ url }) => url);
                setPages(nextPages);
            }
            setPage(targetPage);
            requestAnimationFrame(() => stageRef.current?.querySelector<HTMLElement>(`[data-manual-page="${targetPage}"]`)?.scrollIntoView({ block: 'start', behavior: 'smooth' }));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible d’ouvrir le manuel.');
        } finally { setBusy(false); }
    };

    const download = async () => {
        setBusy(true);
        setError(null);
        try {
            const blob = await userManualService.download();
            const url = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
            const link = document.createElement('a');
            link.href = url;
            link.download = manual?.filename ?? 'manuel-soft-immo.pdf';
            link.click();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de télécharger le manuel.');
        } finally { setBusy(false); }
    };

    return (
        <section id="settings-user-manual" className="settings-section">
            <Card header="Manuel d’utilisation" className="settings-card settings-card--manual">
                {loading ? <p role="status">Chargement du manuel…</p> : manual && <>
                    <p className="settings-manual__description">{manual.description}</p>
                    <p className="settings-manual__meta"><strong>{manual.label}</strong><span>Version {manual.version}</span><span>Mise à jour : {manual.updatedAt}</span></p>
                    <div className="settings-manual__actions">
                        <Button disabled={busy} onClick={() => void openReader(pages.length > 0 ? page : 1)}>{busy ? 'Chargement des pages…' : pages.length > 0 ? 'Reprendre la lecture' : 'Lire le manuel'}</Button>
                        <Button variant="secondary" disabled={busy} onClick={() => void download()}>Télécharger le PDF</Button>
                    </div>
                    {pages.length > 0 && <div className="settings-manual-reader" aria-label={`Lecteur du manuel ${manual.title}`}>
                        <div className="settings-manual-reader__toolbar" role="group" aria-label="Commandes du lecteur">
                            <Button variant="secondary" disabled={busy || page <= 1} onClick={() => void openReader(page - 1)}>Page précédente</Button>
                            <span className="settings-manual-reader__page" aria-live="polite">Page {page} / {manual.pageCount}</span>
                            <Button variant="secondary" disabled={busy || page >= manual.pageCount} onClick={() => void openReader(page + 1)}>Page suivante</Button>
                            <span className="settings-manual-reader__zoom" role="group" aria-label="Zoom">
                                <Button variant="secondary" disabled={zoom <= 60} onClick={() => setZoom((value) => Math.max(60, value - 20))} aria-label="Réduire le zoom">−</Button>
                                <span>{zoom} %</span>
                                <Button variant="secondary" disabled={zoom >= 200} onClick={() => setZoom((value) => Math.min(200, value + 20))} aria-label="Augmenter le zoom">+</Button>
                            </span>
                        </div>
                        <div className="settings-manual-reader__stage" ref={stageRef} tabIndex={0} aria-label="Document : défilez verticalement pour lire les pages" onScroll={(event) => {
                            const stage = event.currentTarget;
                            const marker = stage.getBoundingClientRect().top + 24;
                            const visiblePage = Array.from(stage.querySelectorAll<HTMLElement>('[data-manual-page]'))
                                .filter((element) => element.getBoundingClientRect().bottom > marker)
                                .sort((a, b) => Math.abs(a.getBoundingClientRect().top - marker) - Math.abs(b.getBoundingClientRect().top - marker))[0];
                            const nextPage = Number(visiblePage?.dataset.manualPage);
                            if (nextPage) setPage(nextPage);
                        }}>
                            {pages.map(({ page: pageNumber, url }) => <figure className="settings-manual-reader__document-page" data-manual-page={pageNumber} key={pageNumber}>
                                <img src={url} alt={`Page ${pageNumber} du manuel ${manual.title}`} style={{ width: `${zoom}%` }} draggable={false} />
                            </figure>)}
                        </div>
                    </div>}
                </>}
                {error && <p className="settings-error" role="alert">{error}</p>}
            </Card>
        </section>
    );
}
