import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none">
            <path
                d="M17.5 6.4C16.4 4.9 14.4 4 12 4C8.9 4 6.5 5.6 6.5 8C6.5 10.2 8.2 11.2 11.8 12C15.7 12.9 17.5 14 17.5 16.4C17.5 18.9 15.1 20.5 12 20.5C9.3 20.5 7.1 19.5 5.8 17.7"
                stroke="currentColor"
                strokeWidth="2.6"
                strokeLinecap="round"
            />
            <path
                d="M7.7 9.2H16.3M7.7 15.3H16.3"
                stroke="currentColor"
                strokeWidth="1.25"
                strokeLinecap="round"
                opacity=".55"
            />
        </svg>
    );
}
