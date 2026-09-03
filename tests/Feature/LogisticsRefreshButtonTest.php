<?php

namespace Tests\Feature;

use App\Filament\Resources\LogisticsResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Refresh Statuses" button is injected via a table render hook scoped to
 * ListLogistics specifically so it renders left of the search box, not as a
 * per-guest row action. This just proves it actually lands there.
 */
class LogisticsRefreshButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_statuses_button_renders_before_the_search_field(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $content = $this->get(LogisticsResource::getUrl('index'))->assertSuccessful()->getContent();

        $buttonPos = strpos($content, 'refreshAllStatuses');
        $searchPos = strpos($content, 'fi-ta-search-field');

        $this->assertNotFalse($buttonPos, 'Refresh Statuses button not found on the page.');
        $this->assertNotFalse($searchPos, 'Search field not found on the page.');
        $this->assertLessThan($searchPos, $buttonPos, 'Refresh Statuses button should render before the search field.');
    }
}
