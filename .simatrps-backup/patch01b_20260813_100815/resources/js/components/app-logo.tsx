import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="relative flex aspect-square size-9 items-center justify-center rounded-xl bg-[#008080] text-white shadow-sm shadow-teal-900/15">
                <AppLogoIcon className="size-5" />
                <span className="absolute -right-0.5 -top-0.5 size-2.5 rounded-full border-2 border-sidebar bg-amber-400" />
            </div>

            <div className="ml-1.5 grid flex-1 text-left">
                <span className="truncate text-[15px] leading-tight font-extrabold tracking-tight text-slate-900">
                    SiMatRPS
                </span>
                <span className="truncate text-[10px] font-medium leading-tight text-teal-700/80">
                    RPS Berbasis OBE
                </span>
            </div>
        </>
    );
}
