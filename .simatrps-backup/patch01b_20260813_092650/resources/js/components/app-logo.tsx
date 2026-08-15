import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 text-white dark:text-black" />
            </div>

            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-sm leading-tight font-bold">SiMatRPS</span>
                <span className="truncate text-[10px] leading-tight text-sidebar-foreground/60">
                    RPS Berbasis OBE
                </span>
            </div>
        </>
    );
}
