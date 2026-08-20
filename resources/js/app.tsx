import { createInertiaApp, router } from '@inertiajs/react';
import { LoaderCircle, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import '../css/rps-status-colors.css';
import '../css/cpmk-ai-flow.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function AiProcessingIndicator() {
    const [active, setActive] = useState(false);
    const [message, setMessage] = useState('AI SiMatRPS sedang memproses...');

    useEffect(() => {
        const removeStart = router.on('start', (event: any) => {
            const rawUrl = event?.detail?.visit?.url;
            const url = typeof rawUrl === 'string'
                ? rawUrl
                : (rawUrl?.pathname || String(rawUrl || ''));

            const isAiRequest = url.includes('/ai/') || url.includes('/ai-references');

            if (!isAiRequest) return;

            if (url.includes('/ai/weeks/')) {
                setMessage('AI SiMatRPS sedang menyusun isi pekan...');
            } else if (url.includes('/ai-references')) {
                setMessage('AI SiMatRPS sedang menelaah pustaka...');
            } else {
                setMessage('AI SiMatRPS sedang menelaah RPS...');
            }

            setActive(true);
        });

        const removeFinish = router.on('finish', () => {
            setActive(false);
        });

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    if (!active) return null;

    return (
        <div className="fixed bottom-5 left-1/2 z-[200] -translate-x-1/2 px-3 sm:bottom-6 sm:left-auto sm:right-6 sm:translate-x-0 sm:px-0">
            <div className="flex min-w-[280px] items-center gap-3 rounded-2xl border border-teal-300/70 bg-slate-950/95 px-4 py-3 text-white shadow-2xl shadow-slate-950/30 backdrop-blur">
                <div className="relative flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/20">
                    <LoaderCircle className="size-6 animate-spin text-cyan-300" />
                    <Sparkles className="absolute size-3 text-amber-300" />
                </div>
                <div>
                    <div className="text-xs font-extrabold text-white">Sedang diproses</div>
                    <div className="mt-0.5 text-[11px] leading-4 text-cyan-100">{message}</div>
                    <div className="mt-1 text-[10px] text-white/60">Mohon tunggu, halaman akan diperbarui otomatis.</div>
                </div>
            </div>
        </div>
    );
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <AiProcessingIndicator />
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: false,
});

// This will set light / dark mode on load...
initializeTheme();