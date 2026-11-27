<?php

namespace Tests\Unit\ChessLiveCockpit;

use App\Services\ChessLiveCockpit\ChessRecurringBoardResolver;
use Tests\TestCase;

class ChessRecurringBoardResolverTest extends TestCase
{
    public function test_resolves_direct_board_keys_and_all_configured_core_market_ids(): void
    {
        $resolver = app(ChessRecurringBoardResolver::class);

        $this->assertSame('cms-recurring-tuesday', $resolver->resolve('cms-recurring-tuesday'));
        $this->assertSame('cms-recurring-tuesday', $resolver->resolve('b190e98a-454c-4625-b02c-ada4db850f8c'));
        $this->assertSame('cms-recurring-freestyle-friday', $resolver->resolve('5329540f-cd11-475c-ae0d-27700dfc9f72'));
        $this->assertSame('cms-recurring-3-0-thursday', $resolver->resolve('0e56856e-afe0-47ee-be69-f2e517c7ae84'));
    }

    public function test_persisted_provenance_resolves_unknown_core_id_without_title_guessing(): void
    {
        $resolver = app(ChessRecurringBoardResolver::class);

        $this->assertSame('cms-recurring-3-0-thursday', $resolver->resolve(
            '11111111-2222-4333-8444-555555555555',
            ['cms_recurring_board_key' => 'cms-recurring-3-0-thursday']
        ));
        $this->assertNull($resolver->resolve(
            '11111111-2222-4333-8444-555555555555',
            ['title' => '3+0 Thursday']
        ));
    }

    public function test_conflicting_market_mapping_and_persisted_provenance_fail_closed(): void
    {
        $resolver = app(ChessRecurringBoardResolver::class);

        $this->assertNull($resolver->resolve(
            'b190e98a-454c-4625-b02c-ada4db850f8c',
            ['cms_recurring_board_key' => 'cms-recurring-3-0-thursday']
        ));
        $this->assertNull($resolver->resolve(
            '11111111-2222-4333-8444-555555555555',
            [
                'cms_recurring_board_key' => 'cms-recurring-tuesday',
                'cmsRecurringBoardKey' => 'cms-recurring-3-0-thursday',
            ]
        ));
    }
}
