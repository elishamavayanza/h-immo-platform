import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

function App() {
    return (
        <div>
            <h1>Gestion H-Immo</h1>
            <p>Frontend React 19 + TypeScript</p>
        </div>
    );
}

const rootElement = document.getElementById('root');

if (!rootElement) {
    throw new Error('Root element #root not found');
}

createRoot(rootElement).render(
    <StrictMode>
        <App />
    </StrictMode>,
);
