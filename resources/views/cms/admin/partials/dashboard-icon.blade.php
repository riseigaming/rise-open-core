<svg class="dashboard-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('registers')<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2m4-13v6m-3-3h6"/>@break
        @case('logins')<path d="M14 4h5v16h-5M3 12h12m-4-4 4 4-4 4"/>@break
        @case('approvedDeposits')<rect x="3" y="6" width="18" height="14" rx="3"/><path d="M7 3h10M12 9v7m-3-3 3 3 3-3"/>@break
        @case('approvedWithdraws')<rect x="3" y="6" width="18" height="14" rx="3"/><path d="M7 3h10M12 16V9m-3 3 3-3 3 3"/>@break
        @case('approvedPromotions')<path d="M3 9h18v4H3zm2 4v8h14v-8M12 9v12"/><path d="M12 9H8a3 3 0 1 1 3-3zm0 0h4a3 3 0 1 0-3-3z"/>@break
        @case('net')<path d="M3 17h4l3-9 4 12 3-9h4M3 4h18"/>@break
        @case('overview')<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>@break
        @case('finance')<path d="M20 8V5a2 2 0 0 0-2-2H6a3 3 0 0 0 0 6h15v11H6a3 3 0 0 1-3-3V6"/><path d="M21 12h-6v5h6m-3-2.5h.01"/>@break
        @case('support')<path d="M4 13v-2a8 8 0 0 1 16 0v6a4 4 0 0 1-4 4h-3"/><rect x="3" y="11" width="4" height="7" rx="2"/><rect x="17" y="11" width="4" height="7" rx="2"/>@break
        @case('filters')<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2" fill="white"/><circle cx="16" cy="12" r="2" fill="white"/><circle cx="10" cy="18" r="2" fill="white"/>@break
        @case('refresh')<path d="M20 9a8 8 0 0 0-14-3L3 9m0-6v6h6m-5 6a8 8 0 0 0 14 3l3-3m0 6v-6h-6"/>@break
        @case('queue')<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>@break
        @case('trend')<path d="M3 3v18h18M7 15l4-5 4 2 5-7"/>@break
        @case('provider')<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01M12 7h5m-5 10h5"/>@break
        @case('games')<rect x="3" y="5" width="18" height="14" rx="4"/><path d="M6 12h6m-3-3v6m7-4h.01M18 14h.01"/>@break
        @case('users')<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2m1-14a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 5"/>@break
        @case('shield')<path d="m12 3 8 3v5c0 5-8 10-8 10S4 16 4 11V6l8-3Z"/><path d="m8 12 3 3 5-6"/>@break
        @case('language')<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c-5 5-5 13 0 18m0-18c5 5 5 13 0 18"/>@break
        @case('image')<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="2"/><path d="m3 18 6-6 4 4 4-7 4 5"/>@break
        @case('menu')<path d="M5 6h14M5 12h14M5 18h14"/>@break
        @default <path d="m9 5 7 7-7 7"/>
    @endswitch
</svg>
