@once
    <style>
        .navbarTimedPromotionModal {
            --gelirse-popover-motion-direction-x: -1;
            --gelirse-popover-motion-origin-x: 0%;
            --gelirse-popover-motion-origin: var(--gelirse-popover-motion-origin-x) var(--gelirse-popover-motion-origin-y, 0);
            --gelirse-popover-motion-closed-transform: translate3d(calc(var(--gelirse-popover-motion-direction-x, 1) * var(--gelirse-popover-motion-offset-x, 10px)), var(--gelirse-popover-motion-offset-y, -4px), 0) scale(var(--gelirse-popover-motion-scale, 0.965));
            --navbar-promo-modal-surface: var(--global-surface, #141c27);
            --navbar-promo-modal-border: transparent;
            --navbar-promo-modal-border-strong: rgba(15, 23, 42, 0.08);
            --navbar-promo-modal-text: var(--global-text, #f4f7fb);
            --navbar-promo-modal-muted: var(--global-muted, #91a0b5);
            --navbar-promo-modal-ink: var(--global-text, #f4f7fb);
            --navbar-promo-modal-subtle: var(--global-muted, #91a0b5);
            --navbar-promo-modal-gold: #f1c95f;
            --navbar-promo-modal-teal: #75ead2;
            --navbar-promo-modal-shadow: 0 36px 110px rgba(11, 18, 32, 0.34);
            position: fixed !important;
            top: var(--navbar-promo-popover-top, 56px) !important;
            left: var(--navbar-promo-popover-left, 12px) !important;
            right: auto !important;
            bottom: auto !important;
            width: var(--navbar-promo-popover-width, min(1900px, calc(100vw - 96px))) !important;
            height: auto !important;
            padding: 0 !important;
            overflow: visible !important;
            background: transparent !important;
            opacity: 1 !important;
            transition: none !important;
            pointer-events: none;
            z-index: 6200 !important;
        }

        .navbarTimedPromotionModal[hidden] {
            display: none !important;
        }

        .navbarTimedPromotionModal.is-mounted {
            display: block !important;
            visibility: visible;
        }

        .navbarTimedPromotionModal .modal-dialog,
        .navbarTimedPromotionModal.fade .modal-dialog {
            display: block;
            min-height: 0;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            opacity: 0 !important;
            transform: var(--gelirse-popover-motion-closed-transform, translate3d(calc(var(--gelirse-popover-motion-direction-x, 1) * var(--gelirse-popover-motion-offset-x, 10px)), var(--gelirse-popover-motion-offset-y, -4px), 0) scale(var(--gelirse-popover-motion-scale, 0.965))) !important;
            transform-origin: var(--gelirse-popover-motion-origin, var(--gelirse-popover-motion-origin-x, 100%) var(--gelirse-popover-motion-origin-y, 0)) !important;
            transition:
                opacity var(--gelirse-popover-motion-opacity-duration, 180ms) ease-out,
                transform var(--gelirse-popover-motion-transform-duration, 210ms) var(--gelirse-popover-motion-easing, cubic-bezier(0.22, 1, 0.36, 1)),
                visibility 0s linear var(--gelirse-popover-motion-transform-duration, 210ms) !important;
            visibility: hidden !important;
            will-change: opacity, transform;
            pointer-events: none;
        }

        .navbarTimedPromotionModal.is-opening .modal-dialog,
        .navbarTimedPromotionModal.is-open .modal-dialog,
        .navbarTimedPromotionModal.fade.is-opening .modal-dialog,
        .navbarTimedPromotionModal.fade.is-open .modal-dialog {
            opacity: 1 !important;
            visibility: visible !important;
            transform: var(--gelirse-popover-motion-open-transform, translate3d(0, 0, 0) scale(1)) !important;
            pointer-events: auto;
            transition:
                opacity var(--gelirse-popover-motion-opacity-duration, 180ms) ease-out,
                transform var(--gelirse-popover-motion-transform-duration, 210ms) var(--gelirse-popover-motion-easing, cubic-bezier(0.22, 1, 0.36, 1)),
                visibility 0s !important;
        }

        .navbarTimedPromotionModal.is-closing .modal-dialog,
        .navbarTimedPromotionModal.fade.is-closing .modal-dialog {
            opacity: 0 !important;
            visibility: visible !important;
            transform: var(--gelirse-popover-motion-closed-transform, translate3d(calc(var(--gelirse-popover-motion-direction-x, 1) * var(--gelirse-popover-motion-offset-x, 10px)), var(--gelirse-popover-motion-offset-y, -4px), 0) scale(var(--gelirse-popover-motion-scale, 0.965))) !important;
            pointer-events: none;
        }

        .navbarTimedPromotionModal .modal-content {
            position: relative;
            width: 100%;
            min-height: min(78vh, 780px);
            height: var(--navbar-promo-popover-max-height, auto);
            max-height: var(--navbar-promo-popover-max-height, calc(100vh - 72px));
            border: 0;
            border-radius: 24px;
            background: var(--navbar-promo-modal-surface);
            box-shadow: var(--navbar-promo-modal-shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .navbarTimedPromotionModal .modal-content::before {
            content: "";
            position: absolute;
            top: 14px;
            left: 50%;
            width: 76px;
            height: 6px;
            border-radius: 999px;
            background: transparent;
            transform: translateX(-50%);
            pointer-events: none;
        }

        .navbarTimedPromotionModalA11yTitle {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .navbarTimedPromotionModalClose {
            position: absolute;
            top: 18px;
            right: 18px;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            min-width: 44px;
            border: 0;
            border-radius: 10px;
            background: rgba(56, 76, 118, 0.07);
            color: rgba(62, 81, 117, 0.78);
            cursor: pointer;
            transition: none;
            transform: translateY(0) !important;
        }

        .navbarTimedPromotionModalClose:hover,
        .navbarTimedPromotionModalClose:focus-visible {
            border: 0;
            background: rgba(56, 76, 118, 0.07);
            color: rgba(62, 81, 117, 0.78);
            outline: none;
            transform: translateY(0) !important;
        }

        .navbarTimedPromotionModal .modal-body {
            padding: 0;
            background: var(--navbar-promo-modal-surface) !important;
            overflow: auto;
            overscroll-behavior: contain;
        }

        .navbarTimedPromotionModalBody {
            padding-top: 0;
        }

        .navbarTimedPromotionModalGrid {
            display: flex;
            flex-direction: column;
            gap: 24px;
            min-width: 0;
        }

        .navbarTimedPromotionModalVisual {
            min-height: min(42vh, 420px);
            overflow: hidden;
            border: 1px solid var(--navbar-promo-modal-border-strong);
            border-radius: 28px;
            background:
                linear-gradient(135deg, rgba(25, 37, 86, 0.96) 0%, rgba(12, 18, 42, 0.98) 100%);
            box-shadow: 0 24px 54px rgba(4, 9, 24, 0.32);
        }

        .navbarTimedPromotionModalVisual img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .navbarTimedPromotionModalCopy {
            display: flex;
            flex-direction: column;
            gap: 18px;
            min-width: 0;
            padding: 0 4px;
        }

        .navbarTimedPromotionModalMeta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .navbarTimedPromotionModalBadge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid rgba(138, 160, 232, 0.16);
            color: #f5f8ff;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.03em;
            backdrop-filter: blur(14px);
        }

        .navbarTimedPromotionModalBadge.is-timed {
            background: rgba(94, 131, 255, 0.2);
        }

        .navbarTimedPromotionModalBadge.is-standard {
            background: rgba(120, 92, 255, 0.18);
        }

        .navbarTimedPromotionModalBadge.is-countdown {
            background: rgba(64, 214, 179, 0.16);
            border-color: rgba(64, 214, 179, 0.22);
            color: #ddfff7;
        }

        .navbarTimedPromotionModalBadge.is-active {
            background: rgba(255, 214, 102, 0.16);
            border-color: rgba(255, 214, 102, 0.22);
            color: #fff3cb;
        }

        .navbarTimedPromotionModalHeroCopy {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-width: min(760px, 100%);
        }

        .navbarTimedPromotionModalHeroCopy h3 {
            margin: 0;
            color: var(--navbar-promo-modal-text);
            font-size: clamp(32px, 4vw, 52px);
            font-weight: 900;
            letter-spacing: -0.05em;
            line-height: 0.96;
        }

        .navbarTimedPromotionModalSummary,
        .navbarTimedPromotionModalDescription {
            color: var(--navbar-promo-modal-muted);
            font-size: 15px;
            line-height: 1.78;
        }

        .navbarTimedPromotionModalSummary {
            margin: 0;
            max-width: 680px;
            color: rgba(241, 245, 255, 0.92);
            font-size: 15px;
            line-height: 1.76;
        }

        .navbarTimedPromotionModalDescription {
            padding: 26px 24px;
            border: 1px solid rgba(129, 150, 222, 0.12);
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.035);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.02);
        }

        .navbarTimedPromotionModalDescription > :first-child {
            margin-top: 0;
        }

        .navbarTimedPromotionModalDescription > :last-child {
            margin-bottom: 0;
        }

        .navbarTimedPromotionModalActions {
            position: sticky;
            bottom: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 18px 0 2px;
            margin-top: -4px;
            background: linear-gradient(180deg, rgba(7, 11, 27, 0) 0%, rgba(7, 11, 27, 0.78) 22%, rgba(7, 11, 27, 0.98) 100%);
        }

        .navbarTimedPromotionModalBtn,
        .navbarTimedPromotionModalBtnGhost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 56px;
            padding: 0 22px;
            border-radius: 18px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
        }

        .navbarTimedPromotionModalBtn {
            border: 1px solid rgba(137, 168, 255, 0.28);
            background: linear-gradient(135deg, #5d8fff 0%, #3867ef 100%);
            color: var(--navbar-promo-modal-text);
            box-shadow: 0 18px 40px rgba(43, 96, 255, 0.28);
        }

        .navbarTimedPromotionModalBtnGhost {
            border: 1px solid rgba(138, 160, 232, 0.16);
            background: rgba(255, 255, 255, 0.04);
            color: rgba(244, 247, 255, 0.9);
        }

        .navbarTimedPromotionModalBtn:hover,
        .navbarTimedPromotionModalBtn:focus-visible,
        .navbarTimedPromotionModalBtnGhost:hover,
        .navbarTimedPromotionModalBtnGhost:focus-visible {
            color: #ffffff;
        }

        .navbarTimedPromotionBackdrop {
            position: fixed;
            inset: 0;
            z-index: 6190;
            display: block;
            padding: 0;
            border: 0;
            outline: 0;
            opacity: 0;
            pointer-events: none;
            appearance: none;
            -webkit-appearance: none;
            background: rgba(8, 12, 22, 0.58);
            box-shadow: none;
            transition: opacity var(--gelirse-popover-motion-opacity-duration, 180ms) ease-out;
        }

        .navbarTimedPromotionBackdrop:focus,
        .navbarTimedPromotionBackdrop:focus-visible {
            outline: 0;
            box-shadow: none;
        }

        .navbarTimedPromotionBackdrop.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .navbarTimedPromotionBackdrop.is-closing {
            opacity: 0;
            pointer-events: none;
        }

        .promotionDetailModalContent {
            display: flex;
            flex-direction: column;
            gap: 0;
            min-height: 100%;
            color: var(--navbar-promo-modal-ink);
            background: var(--navbar-promo-modal-surface);
        }

        .promotionDetailModalHero {
            position: relative;
            overflow: hidden;
            min-height: 0;
            border: 0;
            border-radius: 0;
            background: #f5f7fb;
            box-shadow: none;
        }

        .promotionDetailModalVisual,
        .promotionDetailModalVisual img {
            width: 100%;
            height: 100%;
        }

        .promotionDetailModalVisual {
            position: relative;
            aspect-ratio: 160 / 31;
            min-height: 130px;
            max-height: 310px;
        }

        .promotionDetailModalVisual img {
            display: block;
            object-fit: cover;
            filter: none;
            opacity: 1;
        }

        .promotionDetailModalHeroOverlay {
            display: none;
        }

        .promotionDetailModalBadgeRow {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }

        .promotionDetailModalBadge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid rgba(30, 64, 175, 0.12);
            background: #eef4ff;
            color: #244178;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .promotionDetailModalBadge.is-timed {
            border-color: rgba(15, 118, 110, 0.16);
            color: #0f766e;
        }

        .promotionDetailModalBadge.is-standard {
            border-color: rgba(30, 64, 175, 0.12);
        }

        .promotionDetailModalBadge.is-countdown {
            background: #e7fbf6;
            border-color: rgba(15, 118, 110, 0.16);
            color: #0f766e;
        }

        .promotionDetailModalBadge.is-active {
            background: #fff7df;
            border-color: rgba(180, 130, 22, 0.16);
            color: #8a5f0f;
        }

        .promotionDetailModalHeroCopy {
            display: flex;
            align-items: center;
            flex-direction: column;
            gap: 18px;
            max-width: min(760px, 100%);
            align-self: center;
            text-align: center;
        }

        .promotionDetailModalHeroCopy h3 {
            margin: 0;
            color: var(--navbar-promo-modal-text);
            font-size: clamp(30px, 3.2vw, 52px);
            font-weight: 900;
            letter-spacing: 0;
            line-height: 1.08;
            text-shadow: none;
        }

        .promotionDetailModalSummary {
            margin: 0;
            max-width: 760px;
            color: var(--navbar-promo-modal-muted);
            font-size: 18px;
            line-height: 1.45;
        }

        .promotionDetailModalCopy {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            margin-top: 0;
            padding: 34px clamp(24px, 12vw, 240px) 56px;
            border-radius: 0;
            border-top: 0;
            background: var(--navbar-promo-modal-surface);
            color: var(--navbar-promo-modal-subtle);
        }

        .promotionDetailModalSectionTitle {
            width: min(860px, 100%);
            margin: 0;
            color: var(--navbar-promo-modal-text);
            font-size: clamp(20px, 1.6vw, 28px);
            font-weight: 900;
            letter-spacing: 0;
            line-height: 1.15;
        }

        .promotionDetailModalTermsVisual {
            width: min(860px, 100%);
            overflow: hidden;
            border-radius: 28px;
            border: 1px solid rgba(232, 239, 255, 0.1);
            background: rgba(255, 255, 255, 0.035);
        }

        .promotionDetailModalTermsVisual img {
            display: block;
            width: 100%;
            aspect-ratio: 16 / 8;
            object-fit: cover;
        }

        .promotionDetailModalDescription {
            width: min(860px, 100%);
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: var(--navbar-promo-modal-text);
            font-size: 16px;
            line-height: 1.68;
            box-shadow: none;
        }

        .promotionDetailModalDescription img {
            display: block;
            max-width: 100%;
            height: auto;
            margin: 18px auto;
            border-radius: 24px;
        }

        .promotionDetailModalDescription > :first-child {
            margin-top: 0;
        }

        .promotionDetailModalDescription > :last-child {
            margin-bottom: 0;
        }

        .promotionDetailModalPokerGuide {
            display: grid;
            gap: 22px;
            width: min(860px, 100%);
            color: #243557;
        }

        .promotionDetailModalPokerComboBoard {
            display: grid;
            gap: 24px;
            overflow: hidden;
            padding: clamp(22px, 3vw, 34px);
            border-radius: 24px;
            border: 1px solid #dfe7f6;
            background:
                radial-gradient(circle at 18% 0%, rgba(246, 216, 120, 0.18), transparent 32%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            color: #172b5a;
            box-shadow: 0 18px 42px rgba(42, 63, 112, 0.08);
        }

        .promotionDetailModalPokerComboHeader {
            display: grid;
            gap: 8px;
            text-align: center;
        }

        .promotionDetailModalPokerComboHeader span {
            color: #b78718;
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .promotionDetailModalPokerComboHeader h4 {
            margin: 0;
            color: #142348;
            font-size: clamp(28px, 4vw, 48px);
            font-weight: 900;
            letter-spacing: 0;
            line-height: 1.04;
            text-transform: uppercase;
        }

        .promotionDetailModalPokerHands {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px 34px;
        }

        .promotionDetailModalPokerHand {
            display: grid;
            justify-items: center;
            gap: 10px;
            min-width: 0;
        }

        .promotionDetailModalPokerHand strong {
            color: #172b5a;
            font-size: clamp(15px, 1.8vw, 21px);
            font-weight: 900;
            line-height: 1.15;
            text-align: center;
            text-transform: uppercase;
        }

        .promotionDetailModalPokerCards {
            display: flex;
            justify-content: center;
            width: min(100%, 240px);
            min-height: 74px;
            padding-top: 8px;
        }

        .promotionDetailModalPokerCard {
            position: relative;
            display: grid;
            align-content: start;
            gap: 3px;
            flex: 0 0 48px;
            width: 48px;
            height: 68px;
            margin-left: -11px;
            padding: 7px 5px;
            border: 1px solid rgba(15, 23, 42, 0.18);
            border-radius: 6px;
            background:
                linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            color: #111827;
            box-shadow: 0 12px 20px rgba(10, 18, 35, 0.24);
            font-family: Georgia, "Times New Roman", serif;
            transform-origin: 50% 92%;
        }

        .promotionDetailModalPokerCard:first-child {
            margin-left: 0;
        }

        .promotionDetailModalPokerCard:nth-child(1) {
            transform: rotate(-10deg) translateY(4px);
        }

        .promotionDetailModalPokerCard:nth-child(2) {
            transform: rotate(-5deg) translateY(0);
        }

        .promotionDetailModalPokerCard:nth-child(4) {
            transform: rotate(5deg) translateY(0);
        }

        .promotionDetailModalPokerCard:nth-child(5) {
            transform: rotate(10deg) translateY(4px);
        }

        .promotionDetailModalPokerCard.is-red {
            color: #ef233c;
        }

        .promotionDetailModalPokerCard b,
        .promotionDetailModalPokerCard i {
            display: block;
            font-style: normal;
            line-height: 1;
        }

        .promotionDetailModalPokerCard b {
            font-size: 15px;
            font-weight: 900;
        }

        .promotionDetailModalPokerCard i {
            font-size: 21px;
        }

        .promotionDetailModalPokerIntro {
            display: grid;
            gap: 8px;
        }

        .promotionDetailModalPokerIntro span {
            color: #7a8bab;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .promotionDetailModalPokerIntro h4 {
            margin: 0;
            color: #142348;
            font-size: clamp(22px, 1.8vw, 32px);
            font-weight: 900;
            letter-spacing: 0;
            line-height: 1.14;
        }

        .promotionDetailModalPokerIntro p {
            max-width: 780px;
            margin: 0;
            color: #52627f;
            font-size: 16px;
            line-height: 1.6;
        }

        .promotionDetailModalPokerSteps {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .promotionDetailModalPokerStep,
        .promotionDetailModalPokerPanel {
            border: 1px solid #dfe7f6;
            border-radius: 20px;
            background: #f8fbff;
            box-shadow: 0 18px 42px rgba(42, 63, 112, 0.08);
        }

        .promotionDetailModalPokerStep {
            display: grid;
            align-content: start;
            gap: 10px;
            padding: 18px;
            min-height: 170px;
        }

        .promotionDetailModalPokerStepNumber {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 999px;
            background: #172b5a;
            color: #ffd86a;
            font-size: 12px;
            font-weight: 900;
        }

        .promotionDetailModalPokerStep h5,
        .promotionDetailModalPokerPanel h5 {
            margin: 0;
            color: #16274e;
            font-size: 18px;
            font-weight: 900;
            line-height: 1.2;
        }

        .promotionDetailModalPokerStep p {
            margin: 0;
            color: #5e6d89;
            font-size: 14px;
            line-height: 1.5;
        }

        .promotionDetailModalPokerInfoGrid {
            display: grid;
            grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
            gap: 14px;
        }

        .promotionDetailModalPokerPanel {
            padding: 20px;
        }

        .promotionDetailModalPokerRankings {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 14px;
            margin: 16px 0 0;
            padding-left: 22px;
            color: #52627f;
            font-size: 14px;
            line-height: 1.35;
        }

        .promotionDetailModalPokerRules {
            display: grid;
            gap: 12px;
            margin: 16px 0 0;
        }

        .promotionDetailModalPokerRules div {
            display: grid;
            gap: 3px;
        }

        .promotionDetailModalPokerRules dt {
            color: #172b5a;
            font-size: 14px;
            font-weight: 900;
        }

        .promotionDetailModalPokerRules dd {
            margin: 0;
            color: #52627f;
            font-size: 14px;
            line-height: 1.48;
        }

        .promotionDetailModalActions {
            position: static;
            display: flex;
            justify-content: center;
            padding: 6px 0 0;
            margin-top: 4px;
            background: transparent;
        }

        .globalThemeShell .promotionDetailModalPrimary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 54px;
            padding: 0 28px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #f6d878 0%, #d6a93b 100%);
            color: #10182b;
            font-size: 16px;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 18px 36px rgba(214, 169, 59, 0.22);
        }

        .globalThemeShell .promotionDetailModalPrimary:hover,
        .globalThemeShell .promotionDetailModalPrimary:focus-visible {
            color: #10182b;
            text-decoration: none;
        }

        @media (max-width: 991px) {
            .navbarTimedPromotionModal .modal-dialog {
                width: 100% !important;
                max-width: 100% !important;
                min-height: 0;
                margin: 0 !important;
            }

            .navbarTimedPromotionModal .modal-content {
                min-height: min(84vh, 860px);
                max-height: var(--navbar-promo-popover-max-height, calc(100vh - 40px));
                border-radius: 22px;
            }

            .navbarTimedPromotionModal .modal-body {
                padding: 0;
            }

            .navbarTimedPromotionModalVisual {
                min-height: 320px;
            }

            .navbarTimedPromotionModalHeroCopy h3 {
                font-size: clamp(28px, 8vw, 42px);
            }

            .promotionDetailModalHero {
                min-height: 0;
            }

            .promotionDetailModalHeroCopy h3 {
                font-size: clamp(28px, 8vw, 42px);
            }
        }

        @media (max-width: 767px) {
            .navbarTimedPromotionModal .modal-dialog {
                align-items: flex-start !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: 0 !important;
                margin: 0 !important;
            }

            .navbarTimedPromotionModal .modal-content {
                min-height: min(86vh, 760px);
                max-height: var(--navbar-promo-popover-max-height, calc(100vh - 24px));
                border-radius: 16px 16px 0 0;
            }

            .navbarTimedPromotionModalClose {
                top: 16px;
                right: 16px;
            }

            .navbarTimedPromotionModal .modal-body {
                padding: 0;
            }

            .navbarTimedPromotionModalDescription {
                padding: 20px 18px;
            }

            .navbarTimedPromotionModalActions {
                padding-bottom: 0;
            }

            .promotionDetailModalHero {
                min-height: 0;
                border-radius: 0;
            }

            .promotionDetailModalVisual {
                aspect-ratio: 160 / 31;
                min-height: 116px;
            }

            .promotionDetailModalHeroOverlay {
                display: none;
            }

            .promotionDetailModalHeroCopy h3 {
                max-width: none;
                font-size: clamp(28px, 8vw, 40px);
                line-height: 1.14;
            }

            .promotionDetailModalSummary {
                max-width: 330px;
                font-size: 18px;
                line-height: 1.5;
            }

            .promotionDetailModalCopy {
                position: relative;
                z-index: 2;
                align-items: stretch;
                gap: 20px;
                margin-top: 0;
                padding: 28px 24px 42px;
                border-radius: 0;
            }

            .promotionDetailModalSectionTitle,
            .promotionDetailModalTermsVisual,
            .promotionDetailModalDescription {
                width: 100%;
            }

            .promotionDetailModalSectionTitle {
                font-size: 28px;
            }

            .promotionDetailModalTermsVisual {
                border-radius: 24px;
            }

            .promotionDetailModalTermsVisual img {
                aspect-ratio: 1.78 / 1;
            }

            .promotionDetailModalDescription {
                color: #161a22;
                font-size: 17px;
                line-height: 1.55;
            }

            .promotionDetailModalPokerGuide {
                gap: 18px;
            }

            .promotionDetailModalPokerComboBoard {
                gap: 20px;
                padding: 22px 16px;
                border-radius: 20px;
            }

            .promotionDetailModalPokerComboHeader h4 {
                font-size: clamp(26px, 8vw, 36px);
            }

            .promotionDetailModalPokerHands {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .promotionDetailModalPokerCards {
                width: min(100%, 230px);
                min-height: 70px;
            }

            .promotionDetailModalPokerCard {
                flex-basis: 45px;
                width: 45px;
                height: 64px;
                margin-left: -10px;
            }

            .promotionDetailModalPokerIntro p {
                font-size: 15px;
                line-height: 1.55;
            }

            .promotionDetailModalPokerSteps,
            .promotionDetailModalPokerInfoGrid {
                grid-template-columns: 1fr;
            }

            .promotionDetailModalPokerStep {
                min-height: auto;
                padding: 16px;
            }

            .promotionDetailModalPokerRankings {
                grid-template-columns: 1fr;
            }

            .promotionDetailModalPrimary {
                min-height: 64px;
                min-width: min(168px, 72vw);
                padding: 0 30px;
                border-radius: 18px;
                font-size: 18px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .navbarTimedPromotionModal .modal-dialog,
            .navbarTimedPromotionModal.fade .modal-dialog,
            .navbarTimedPromotionModal.is-opening .modal-dialog,
            .navbarTimedPromotionModal.fade.is-opening .modal-dialog,
            .navbarTimedPromotionModal.is-open .modal-dialog,
            .navbarTimedPromotionModal.fade.is-open .modal-dialog,
            .navbarTimedPromotionModal.is-closing .modal-dialog,
            .navbarTimedPromotionModal.fade.is-closing .modal-dialog {
                transform: none !important;
                transition: opacity 60ms ease, visibility 0s linear 60ms !important;
                will-change: opacity;
            }

            .navbarTimedPromotionModal.is-opening .modal-dialog,
            .navbarTimedPromotionModal.fade.is-opening .modal-dialog,
            .navbarTimedPromotionModal.is-open .modal-dialog,
            .navbarTimedPromotionModal.fade.is-open .modal-dialog {
                transition: opacity 60ms ease, visibility 0s !important;
            }
        }
    </style>
@endonce

@php
    $promotionDisplayTitle = data_get($promotion, 'display_detail_title')
        ?: data_get($promotion, 'display_listing_title')
        ?: data_get($promotion, 'translation.detail_title')
        ?: data_get($promotion, 'translation.listing_title');
@endphp

<div class="modal fade navbarTimedPromotionModal" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalId }}Label" aria-hidden="true" data-navbar-timed-promotion-panel data-promo-state="closed" hidden>
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <h2 class="navbarTimedPromotionModalA11yTitle" id="{{ $modalId }}Label">{{ $promotionDisplayTitle }}</h2>
            <button type="button" class="navbarTimedPromotionModalClose cmsModalClose" data-navbar-timed-promotion-close aria-label="{{ trans('cms_public.common.close') }}">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-body navbarTimedPromotionModalBody">
                @include('cms.global.partials.promotion-modal-inner', ['promotion' => $promotion])
            </div>
        </div>
    </div>
</div>

@once
    @push('js')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var activePanel = null;
                var activeTrigger = null;
                var panelState = new WeakMap();
                var activeBackdrop = null;
                var backdropTimer = 0;

                function popoverMotionDelayMs(isClosing) {
                    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        return 0;
                    }

                    var styles = window.getComputedStyle ? window.getComputedStyle(document.documentElement) : null;
                    var property = isClosing ? '--winx-modal-exit-duration' : '--winx-modal-enter-duration';
                    var duration = styles ? parseFloat(styles.getPropertyValue(property)) : 0;

                    return duration > 0 ? duration : (isClosing ? 90 : 180);
                }

                function stateFor(panel) {
                    if (!panelState.has(panel)) {
                        panelState.set(panel, { state: 'closed', version: 0, trigger: null, frame: 0, timer: 0 });
                    }

                    return panelState.get(panel);
                }

                function panelFromTrigger(trigger) {
                    var target = trigger && trigger.getAttribute('data-bs-target');

                    if (!target || target.charAt(0) !== '#') {
                        return null;
                    }

                    try {
                        return document.querySelector(target);
                    } catch (error) {
                        return null;
                    }
                }

                function setTriggerExpanded(trigger, isExpanded) {
                    if (trigger) {
                        trigger.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                    }
                }

                function clearTimers(panel) {
                    var info = stateFor(panel);

                    if (info.frame) {
                        window.cancelAnimationFrame(info.frame);
                        info.frame = 0;
                    }

                    if (info.timer) {
                        window.clearTimeout(info.timer);
                        info.timer = 0;
                    }
                }

                function ensureBackdrop() {
                    if (activeBackdrop && document.body.contains(activeBackdrop)) {
                        return activeBackdrop;
                    }

                    activeBackdrop = document.createElement('div');
                    activeBackdrop.className = 'navbarTimedPromotionBackdrop';
                    activeBackdrop.setAttribute('aria-hidden', 'true');
                    activeBackdrop.setAttribute('data-navbar-timed-promotion-backdrop', '');
                    activeBackdrop.addEventListener('click', function () {
                        if (activePanel) {
                            closePanel(activePanel, false);
                        }
                    });
                    document.body.appendChild(activeBackdrop);

                    return activeBackdrop;
                }

                function ensurePanelLayer(panel) {
                    if (!panel || panel.parentNode === document.body) {
                        return;
                    }

                    document.body.appendChild(panel);
                    panel.setAttribute('data-navbar-timed-promotion-portal', '');
                }

                function openBackdrop() {
                    if (backdropTimer) {
                        window.clearTimeout(backdropTimer);
                        backdropTimer = 0;
                    }

                    var backdrop = ensureBackdrop();
                    backdrop.classList.remove('is-closing');
                    backdrop.offsetWidth;
                    backdrop.classList.add('is-open');
                }

                function closeBackdrop() {
                    if (!activeBackdrop) {
                        return;
                    }

                    if (backdropTimer) {
                        window.clearTimeout(backdropTimer);
                        backdropTimer = 0;
                    }

                    activeBackdrop.classList.remove('is-open');
                    activeBackdrop.classList.add('is-closing');
                    backdropTimer = window.setTimeout(function () {
                        backdropTimer = 0;
                        if (activeBackdrop && !activePanel) {
                            activeBackdrop.remove();
                            activeBackdrop = null;
                        }
                    }, popoverMotionDelayMs(true));
                }

                function positionPanel(trigger, panel) {
                    var triggerRect = trigger.getBoundingClientRect();
                    var viewportWidth = document.documentElement.clientWidth || window.innerWidth || 0;
                    var viewportHeight = document.documentElement.clientHeight || window.innerHeight || 0;
                    var gutter = viewportWidth <= 767 ? 12 : 48;
                    var expansion = 20;
                    var expandedGutter = Math.max(8, gutter - expansion);
                    var placement = trigger.getAttribute('data-navbar-timed-promotion-placement') || 'trigger';
                    var desiredWidth = viewportWidth <= 767
                        ? Math.max(280, viewportWidth - (expandedGutter * 2))
                        : Math.min(1980, viewportWidth - (expandedGutter * 2));
                    var width = Math.max(280, desiredWidth);
                    var left = Math.max(expandedGutter, (viewportWidth - width) / 2);
                    var triggerTop = triggerRect.bottom + 8 - expansion;
                    var top = placement === 'viewport'
                        ? expandedGutter
                        : Math.min(Math.max(triggerTop, expandedGutter), Math.max(expandedGutter, viewportHeight - 120));
                    var maxHeight = Math.max(240, viewportHeight - top - expandedGutter);

                    panel.style.setProperty('--navbar-promo-popover-left', left.toFixed(2) + 'px');
                    panel.style.setProperty('--navbar-promo-popover-top', top.toFixed(2) + 'px');
                    panel.style.setProperty('--navbar-promo-popover-width', width.toFixed(2) + 'px');
                    panel.style.setProperty('--navbar-promo-popover-max-height', maxHeight.toFixed(2) + 'px');
                }

                function finishOpen(panel, version) {
                    var info = stateFor(panel);

                    if (info.version !== version) {
                        return;
                    }

                    info.state = 'open';
                    info.timer = 0;
                    panel.classList.remove('is-opening');
                    panel.classList.add('is-open');
                    panel.setAttribute('data-promo-state', 'open');
                }

                function finishClose(panel, version, shouldRestoreFocus) {
                    var info = stateFor(panel);

                    if (info.version !== version) {
                        return;
                    }

                    info.state = 'closed';
                    info.timer = 0;
                    panel.hidden = true;
                    panel.classList.remove('is-mounted', 'is-opening', 'is-open', 'is-closing');
                    panel.setAttribute('data-promo-state', 'closed');
                    panel.setAttribute('aria-hidden', 'true');

                    if (activePanel === panel) {
                        activePanel = null;
                        activeTrigger = null;
                        closeBackdrop();
                    }

                    if (shouldRestoreFocus && info.trigger && typeof info.trigger.focus === 'function') {
                        info.trigger.focus({ preventScroll: true });
                    }
                }

                function openPanel(trigger, panel) {
                    if (!trigger || !panel) {
                        return;
                    }

                    if (activePanel && activePanel !== panel) {
                        closePanel(activePanel, false);
                    }

                    clearTimers(panel);

                    var info = stateFor(panel);
                    var version = info.version + 1;
                    info.version = version;
                    info.state = 'opening';
                    info.trigger = trigger;

                    activePanel = panel;
                    activeTrigger = trigger;
                    ensurePanelLayer(panel);
                    positionPanel(trigger, panel);
                    openBackdrop();
                    panel.hidden = false;
                    panel.classList.add('is-mounted');
                    panel.classList.remove('is-open', 'is-closing');
                    panel.setAttribute('data-promo-state', 'opening');
                    panel.setAttribute('aria-hidden', 'false');
                    setTriggerExpanded(trigger, true);
                    panel.offsetWidth;
                    panel.classList.add('is-opening');
                    info.timer = window.setTimeout(function () {
                        finishOpen(panel, version);
                    }, popoverMotionDelayMs(false));
                }

                function closePanel(panel, shouldRestoreFocus) {
                    if (!panel) {
                        return;
                    }

                    clearTimers(panel);

                    var info = stateFor(panel);

                    if (info.state === 'closed') {
                        setTriggerExpanded(info.trigger, false);
                        return;
                    }

                    var version = info.version + 1;
                    info.version = version;
                    info.state = 'closing';
                    panel.classList.remove('is-opening', 'is-open');
                    panel.classList.add('is-closing');
                    panel.setAttribute('data-promo-state', 'closing');
                    setTriggerExpanded(info.trigger, false);

                    info.timer = window.setTimeout(function () {
                        finishClose(panel, version, shouldRestoreFocus);
                    }, popoverMotionDelayMs(true));
                }

                document.addEventListener('click', function (event) {
                    var trigger = event.target.closest('[data-navbar-timed-promotion-trigger], [data-bs-toggle="modal"][data-bs-target^="#navbarTimedPromotionModal"]');

                    if (trigger) {
                        var panel = panelFromTrigger(trigger);

                        if (!panel || !panel.classList.contains('navbarTimedPromotionModal')) {
                            return;
                        }

                        event.preventDefault();
                        event.stopPropagation();

                        var info = stateFor(panel);

                        if (info.state === 'open' || info.state === 'opening') {
                            closePanel(panel, true);
                        } else {
                            openPanel(trigger, panel);
                        }

                        return;
                    }

                    if (activePanel && !activePanel.contains(event.target) && (!activeTrigger || !activeTrigger.contains(event.target))) {
                        closePanel(activePanel, false);
                    }
                });

                document.addEventListener('click', function (event) {
                    var closeButton = event.target.closest('[data-navbar-timed-promotion-close]');

                    if (closeButton) {
                        var panel = closeButton.closest('.navbarTimedPromotionModal');
                        event.preventDefault();
                        closePanel(panel, true);
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && activePanel) {
                        event.preventDefault();
                        closePanel(activePanel, true);
                    }
                });

                window.addEventListener('resize', function () {
                    if (activePanel && activeTrigger) {
                        positionPanel(activeTrigger, activePanel);
                    }
                });

                window.addEventListener('scroll', function () {
                    if (activePanel && activeTrigger) {
                        positionPanel(activeTrigger, activePanel);
                    }
                }, true);
            });
        </script>
    @endpush
@endonce
