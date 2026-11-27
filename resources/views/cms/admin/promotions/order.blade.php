@extends('cms.admin.index')

@section('style')
    <style>
        .promotionOrderShell {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .promotionOrderHint {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            color: #6c757d;
            font-size: 0.88rem;
        }

        .promotionOrderList {
            display: grid;
            gap: 12px;
        }

        .promotionOrderCard {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px;
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
            cursor: grab;
            transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
        }

        .promotionOrderCard.is-dragging {
            opacity: 0.55;
            transform: scale(0.985);
            cursor: grabbing;
        }

        .promotionOrderCard.is-over {
            box-shadow: 0 0 0 3px rgba(32, 201, 151, 0.28), 0 18px 40px rgba(8, 15, 35, 0.1);
        }

        .promotionOrderThumb {
            width: 124px;
            flex: 0 0 124px;
            border-radius: 14px;
            overflow: hidden;
            background: #f8fafc;
        }

        .promotionOrderThumb img {
            display: block;
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
        }

        .promotionOrderBody {
            min-width: 0;
            flex: 1 1 auto;
        }

        .promotionOrderTopLine {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .promotionOrderTitle {
            margin: 0;
            color: #111827;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.35;
        }

        .promotionOrderMeta {
            margin-top: 6px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.45;
        }

        .promotionOrderBadge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 26px;
            padding: 0 10px;
            border-radius: 999px;
            background: #e8eefc;
            color: #284fa8;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .promotionOrderHandle {
            color: #94a3b8;
            font-size: 16px;
            flex: 0 0 auto;
        }

        .promotionOrderToolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
    </style>
@endsection

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-sm-12 mb-3">
                <div class="promotionOrderToolbar">
                    <div>
                        <h3 class="mb-1">{{ __('admin.copy.campaign_order') }}</h3>
                        <p class="text-muted mb-0">{{ __('admin.copy.campaign_order_help') }}</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('cms.admin.promotions.index') }}" class="btn btn-outline-secondary">{{ __('admin.copy.back_to_list') }}</a>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        @if($promotions->isEmpty())
                            <div class="text-muted">{{ __('admin.copy.no_campaigns_to_sort') }}</div>
                        @else
                            <form method="POST" action="{{ route('cms.admin.promotions.updateOrder') }}" id="promotionOrderForm">
                                @csrf
                                <div class="promotionOrderShell">
                                    <div class="promotionOrderHint">
                                        <span>{{ __('admin.copy.drag_to_reorder') }}</span>
                                        <span>{{ __('admin.copy.campaigns_sorted', ['count' => $promotions->count()]) }}</span>
                                    </div>

                                    <div class="promotionOrderList" id="promotionOrderList">
                                        @foreach($promotions as $index => $promotion)
                                            @php
                                                $translation = $promotion->translations->first();
                                                $listingTitle = $translation->listing_title ?? $translation->detail_title ?? __('admin.copy.untitled_campaign');
                                                $detailTitle = $translation->detail_title ?? '';
                                            @endphp
                                            <article class="promotionOrderCard" draggable="true" data-promotion-id="{{ $promotion->id }}">
                                                <div class="promotionOrderThumb">
                                                    <img src="{{ $promotion->getThumbnail() }}" alt="{{ $listingTitle }}" loading="lazy">
                                                </div>
                                                <div class="promotionOrderBody">
                                                    <div class="promotionOrderTopLine">
                                                        <h4 class="promotionOrderTitle">{{ __('admin_extra.2d9faadc2657', ['value1' => $listingTitle]) }}</h4>
                                                        <span class="promotionOrderBadge">{{ __('admin_extra.067675b8c470', ['value1' => $index + 1]) }}</span>
                                                    </div>
                                                    <div class="promotionOrderMeta">
                                                        <div>{{ $detailTitle ?: __('admin.copy.missing_detail_title') }}</div>
                                                        <div>{{ __('admin.ui.order') }}: {{ $promotion->rank ?? '-' }} | {{ __('admin.ui.status') }}: {{ $promotion->status === 'active' ? __('admin.ui.active') : __('admin.ui.inactive') }}</div>
                                                    </div>
                                                </div>
                                                <span class="promotionOrderHandle"><i class="fas fa-grip-vertical"></i></span>
                                            </article>
                                        @endforeach
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">{{ __('admin.ui.save_order') }}</button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (() => {
            const form = document.getElementById('promotionOrderForm');
            const list = document.getElementById('promotionOrderList');

            if (!form || !list) {
                return;
            }

            let draggingCard = null;

            const cards = () => Array.from(list.querySelectorAll('.promotionOrderCard'));

            const refreshBadges = () => {
                cards().forEach((card, index) => {
                    const badge = card.querySelector('.promotionOrderBadge');
                    if (badge) {
                        badge.textContent = `#${index + 1}`;
                    }
                });
            };

            const syncHiddenInputs = () => {
                form.querySelectorAll('input[name="ordered_ids[]"]').forEach((input) => input.remove());

                cards().forEach((card) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ordered_ids[]';
                    input.value = card.dataset.promotionId;
                    form.appendChild(input);
                });
            };

            const attachDnD = (card) => {
                card.addEventListener('dragstart', () => {
                    draggingCard = card;
                    card.classList.add('is-dragging');
                });

                card.addEventListener('dragend', () => {
                    card.classList.remove('is-dragging');
                    cards().forEach((item) => item.classList.remove('is-over'));
                    draggingCard = null;
                    refreshBadges();
                    syncHiddenInputs();
                });

                card.addEventListener('dragover', (event) => {
                    event.preventDefault();

                    if (!draggingCard || draggingCard === card) {
                        return;
                    }

                    const rect = card.getBoundingClientRect();
                    const insertBefore = event.clientY < rect.top + (rect.height / 2);
                    card.classList.add('is-over');

                    if (insertBefore) {
                        list.insertBefore(draggingCard, card);
                    } else {
                        list.insertBefore(draggingCard, card.nextSibling);
                    }
                });

                card.addEventListener('dragleave', () => {
                    card.classList.remove('is-over');
                });

                card.addEventListener('drop', (event) => {
                    event.preventDefault();
                    card.classList.remove('is-over');
                });
            };

            cards().forEach(attachDnD);
            syncHiddenInputs();
        })();
    </script>
@endsection
