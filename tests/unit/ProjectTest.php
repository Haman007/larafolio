<?php

namespace Larafolio\tests\unit;

use Larafolio\Models\Image;
use Larafolio\Models\Project;
use Larafolio\tests\TestCase;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ProjectTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function user_can_add_a_project()
    {
        $data = [
            'name' => 'name',
            'type' => 'site',
        ];

        $project = $this->user->addProject($data);

        $this->assertInstanceOf(Project::class, $project);

        $this->seeInDatabase('projects', $data);
    }

    /**
     * @test
     */
    public function order_value_is_set_to_next_avilable_value()
    {
        $max = Project::all()->pluck('order')->max() + 1;

        $dataArray = [
            $max => [
                'name' => 'name1',
                'type' => 'site1',
            ],
            $max + 1 => [
                'name' => 'name2',
                'type' => 'site2',
            ],
            $max + 2 => [
                'name' => 'name3',
                'type' => 'site3',
            ],
        ];

        foreach ($dataArray as $key => $data) {
            $project = $this->user->addProject($data);

            $data['order'] = $key;

            $this->seeInDatabase('projects', $data);
        }
    }

    /**
     * @test
     */
    public function slug_is_created_when_project_is_added()
    {
        $data = [
            'name' => 'project name',
        ];

        $project = $this->user->addProject($data);

        $this->assertEquals('project_name', $project->slug());

        $data['slug'] = $project->slug();

        $this->seeInDatabase('projects', $data);
    }

    /**
     * @test
     */
    public function user_can_update_a_project()
    {
        $project = factory(Project::class)->create();

        $data = [
            'type' => 'new link',
        ];

        $this->user->updateProject($project, $data);

        $merged = collect($project->getAttributes())->merge($data)->all();

        $this->seeInDatabase('projects', $merged);
    }

    /**
     * @test
     */
    public function user_can_update_visibility()
    {
        $project = factory(Project::class)->create();

        $this->seeInDatabase('projects', [
            'id'      => $project->id(),
            'visible' => false,
        ]);

        $data = [
            'visible' => true,
        ];

        $this->user->updateProject($project, $data);

        $this->seeInDatabase('projects', [
            'id'      => $project->id(),
            'visible' => true,
        ]);
    }

    /**
     * @test
     */
    public function slug_is_updated_when_name_is_updated()
    {
        $project = factory(Project::class)->create(['name' => 'first name']);

        $this->assertEquals('first_name', $project->slug());

        $project = $this->user->updateProject($project, ['name' => 'second name']);

        $this->assertEquals('second_name', $project->slug());
    }

    /**
     * @test
     */
    public function user_can_remove_a_project()
    {
        $project = factory(Project::class)->create();

        $this->seeInDatabase('projects', [
            'id'         => $project->id(),
            'deleted_at' => null,
        ]);

        $deleted = $this->user->removeProject($project);

        $this->assertTrue($deleted);

        $this->dontSeeInDatabase('projects', [
            'id'         => $project->id(),
            'deleted_at' => null,
        ]);

        $this->seeInDatabase('projects', [
            'id' => $project->id(),
        ]);
    }

    /**
     * @test
     */
    public function user_can_hard_delete_a_project()
    {
        $project = factory(Project::class)->create();

        $this->seeInDatabase('projects', [
            'id'         => $project->id(),
            'deleted_at' => null,
        ]);
        
        $this->user->removeProject($project);

        $deleted = $this->user->purgeProject($project);

        $this->assertTrue($deleted);

        $this->dontSeeInDatabase('projects', [
            'id'         => $project->id()
        ]);
    }

    /**
     * @test
     */
    public function user_can_restore_a_soft_deleted_project()
    {
        $project = factory(Project::class)->create();

        $this->seeInDatabase('projects', [
            'id'         => $project->id(),
            'deleted_at' => null,
        ]);

        $deleted = $this->user->removeProject($project);

        $this->assertTrue($deleted);

        $this->dontSeeInDatabase('projects', [
            'id'         => $project->id(),
            'deleted_at' => null,
        ]);

        $this->user->restoreProject($project);

        $this->seeInDatabase('projects', [
            'id'         => $project->id(),
            'deleted_at' => null,
        ]);
    }

    /**
     * @test
     */
    public function all_visible_static_returns_all_visible_projects()
    {
        $project1 = factory(Project::class)->create();
        $project2 = factory(Project::class)->create();
        $project3 = factory(Project::class)->create();

        $this->user->updateProject($project1, ['visible' => true]);

        $projects = Project::allVisible()->flatten(1);

        $projects->each(function ($project) {
            $this->assertTrue($project->visible);
        });

        $this->assertCount(1, $projects);
    }

    /**
     * @test
     */
    public function all_visible_static_groups_visible_projects_by_type()
    {
        $this->makeProjectsForAllVisibleTest();

        $visible = Project::allVisible();

        $this->assertCount(1, $visible);

        $this->assertEquals('web', array_keys($visible->all())[0]);

        $this->user->updateProject(Project::first(), [
            'type' => 'open source'
        ]);

        $visible = Project::allVisible();

        $possible = ['web', 'open source'];

        $this->assertContains(array_keys($visible->all())[0], $possible);

        $key = array_search(array_keys($visible->all())[0], $possible);

        unset($possible[$key]);

        $this->assertContains(array_keys($visible->all())[1], $possible);
    }

    /**
     * @test
     */
    public function all_visible_static_grouping_can_be_turned_off()
    {
        $this->makeProjectsForAllVisibleTest();

        $visible = Project::allVisible(false);

        $visible->each(function ($project) {
            $this->assertInstanceOf(Project::class, $project);
        });
    }

    /**
     * @test
     */
    public function all_visible_static_conditionally_orders_visible_projects_by_order()
    {
        $this->makeProjectsForAllVisibleTest();
        $this->makeProjectsForAllVisibleTest();
        $this->makeProjectsForAllVisibleTest();
        $this->makeProjectsForAllVisibleTest();

        $unordered = Project::allVisible(false, false)->pluck('order');

        $ordered = Project::allVisible(false, true)->pluck('order');

        $this->assertOrder($ordered);

        $this->assertNotEquals($unordered, $ordered);
    }

    /**
     * @test
     */
    public function all_hidden_static_returns_all_hidden_projects()
    {
        $project1 = factory(Project::class)->create();
        $project2 = factory(Project::class)->create();
        $project3 = factory(Project::class)->create();

        $this->user->updateProject($project1, ['visible' => true]);

        $projects = Project::allHidden()->flatten(1);

        $projects->each(function ($project) {
            $this->assertFalse($project->visible);
        });

        $hiddenProjects = Project::where('visible', false);

        $this->assertCount($hiddenProjects->count(), $projects);
    }

    /**
     * @test
     */
    public function all_grouped_static_returns_all_projects_grouped()
    {
        $this->makeProjectsForAllVisibleTest();
        $this->makeProjectsForAllVisibleTest(false);

        $projects = Project::allGrouped();

        $this->assertCount(1, $projects);

        $this->assertEquals('web', array_keys($projects->all())[0]);

        $count = Project::all()->count();

        $this->assertCount($count, $projects->flatten(1));
    }

    /**
     * @test
     */
    public function all_ordered_static_returns_all_projects_ordered()
    {
        $this->makeProjectsForAllVisibleTest();
        $this->makeProjectsForAllVisibleTest(false);
        $this->makeProjectsForAllVisibleTest(false, 'github');
        $this->makeProjectsForAllVisibleTest(true, 'github');

        $ordered = Project::allOrdered()->pluck('order');

        $this->assertOrder($ordered);
    }

    /**
     * @test
     */
    public function user_can_get_all_projects_that_have_block_name()
    {
        $project = $this->createProjectWithBlock('block name');
        $project = $this->createProjectWithBlock('block name');
        $project = $this->createProjectWithBlock('block name');
        $project = $this->createProjectWithBlock('other name');

        $projects = Project::hasBlockNamed('block name');

        $this->assertCount(3, $projects);

        $projects->each(function ($project) {
            $block = $project->blocks[0];

            $this->assertEquals('block name', $block->name());
        });
    }

    /**
     * @test
     */
    public function user_can_get_all_projects_that_have_image_name()
    {
        $project = $this->makeProjectWithImage('image name');
        $project = $this->makeProjectWithImage('image name');
        $project = $this->makeProjectWithImage('image name');
        $project = $this->makeProjectWithImage('other name');

        $projects = Project::hasImageNamed('image name');

        $this->assertCount(3, $projects);

        $projects->each(function ($project) {
            $image = $project->images[0];

            $this->assertEquals('image name', $image->name());
        });
    }

    /**
     * @test
     */
    public function user_can_get_all_projects_that_have_link_name()
    {
        $project = $this->makeProjectWithLink('link name');
        $project = $this->makeProjectWithLink('link name');
        $project = $this->makeProjectWithLink('link name');
        $project = $this->makeProjectWithLink('other name');

        $projects = Project::hasLinkNamed('link name');

        $this->assertCount(3, $projects);

        $projects->each(function ($project) {
            $link = $project->links[0];

            $this->assertEquals('link name', $link->name());
        });
    }

    /**
     * @test
     */
    public function ordered_project_blocks_are_ordered_by_block_order()
    {
        $project = $this->createProjectWithBlock('block name');

        $data1 = [
            'name'           => 'text block name',
            'text'           => 'text block text',
            'formatted_text' => '<p>text block text</p>',
            'order'          => 3,
        ];

        $project->blocks()->create($data1);

        $data2 = [
            'name'           => 'text block name',
            'text'           => 'text block text',
            'formatted_text' => '<p>text block text</p>',
            'order'          => 2,
        ];

        $project->blocks()->create($data1);

        $projects = Project::allOrdered();

        foreach ($projects as $project) {
            $blocks = $project->blocks;

            $this->assertOrder($blocks->pluck('order'));
        }
    }

    /**
     * @test
     */
    public function ordered_projects_links_are_ordered_by_link_order()
    {
        $project = $this->makeProjectWithLink('link name');

        $data1 = [
            'name'  => 'new name',
            'text'  => 'new text',
            'url'   => 'new url',
            'order' => 3
        ];

        $project->links()->create($data1);

        $data2 = [
            'name'  => 'new name',
            'text'  => 'new text',
            'url'   => 'new url',
            'order' => 2
        ];

        $project->links()->create($data2);

        $projects = Project::allOrdered();

        foreach ($projects as $project) {
            $links = $project->links;

            $this->assertOrder($links->pluck('order'));
        }
    }

    /**
     * @test
     */
    public function get_project_block_returns_block_named_after_project()
    {
        $project = factory(Project::class)->create();

        $project->blocks()->create([
            'name'           => 'name1',
            'text'           => 'text',
            'formatted_text' => 'formatted',
            'order' => 5
        ]);

        $project->blocks()->create([
            'name'           => $project->name(),
            'text'           => 'text',
            'formatted_text' => 'formatted',
            'order' => 5
        ]);

        $project->blocks()->create([
            'name'           => 'name2',
            'text'           => 'text',
            'formatted_text' => 'formatted',
            'order' => 5
        ]);

        $projectBlock = $project->getProjectBlock();

        $this->assertEquals($project->name(), $projectBlock->name());
    }

    /**
     * @test
     */
    public function get_project_block_returns_first_block_if_no_block_named_after_project()
    {
        $project = factory(Project::class)->create();

        $project->blocks()->create([
            'name'           => 'name1',
            'text'           => 'text',
            'formatted_text' => 'formatted',
            'order' => 5
        ]);

        $project->blocks()->create([
            'name'           => 'name2',
            'text'           => 'text',
            'formatted_text' => 'formatted',
            'order' => 5
        ]);

        $projectBlock = $project->getProjectBlock();

        $this->assertEquals('name1', $projectBlock->name());
    }

    /**
     * @test
     */
    public function get_project_block_returns_null_if_no_blocks()
    {
        $project = factory(Project::class)->create();

        $projectBlock = $project->getProjectBlock();

        $this->assertEquals(null, $projectBlock);
    }

    /**
     * @test
     */
    public function can_get_project_block_formatted_text()
    {
        $project = $this->createProjectWithBlock();

        $formatted = $project->getProjectBlockText();

        $this->assertEquals('formatted', $formatted);
    }

    /**
     * @test
     */
    public function can_get_project_block_unformatted_text()
    {
        $project = $this->createProjectWithBlock();

        $formatted = $project->getProjectBlockText(false);

        $this->assertEquals('text', $formatted);
    }

    /**
     * @test
     */
    public function get_project_image_returns_image_named_after_project()
    {
        $project = factory(Project::class)->create();

        $project->images()->save(factory(Image::class)->make([
            'name' => 'name1',
        ]));

        $project->images()->save(factory(Image::class)->make([
            'name' => $project->name(),
        ]));

        $project->images()->save(factory(Image::class)->make([
            'name' => 'name2',
        ]));

        $projectImage = $project->getProjectImage();

        $this->assertEquals($project->name(), $projectImage->name());
    }

    /**
     * @test
     */
    public function get_project_image_returns_first_image_if_no_image_named_after_project()
    {
        $project = factory(Project::class)->create();

        $project->images()->save(factory(Image::class)->make([
            'name' => 'name1',
        ]));

        $project->images()->save(factory(Image::class)->make([
            'name' => 'name2',
        ]));

        $projectImage = $project->getProjectImage();

        $this->assertEquals('name1', $projectImage->name());
    }


    /**
     * @test
     */
    public function get_project_image_returns_null_if_no_images()
    {
        $project = factory(Project::class)->create();

        $projectImage = $project->getProjectImage();

        $this->assertEquals(null, $projectImage);
    }

    /**
     * @test
     */
    public function can_get_project_image_url()
    {
        $project = factory(Project::class)->create();

        $project->images()->save(factory(Image::class)->make([
            'path' => 'url',
        ]));

        $url = $project->getProjectImageUrl();

        $this->assertEquals('/manager/images/small/url', $url);
    }

    /**
     * @test
     */
    public function can_get_project_image_url_by_size()
    {
        $project = factory(Project::class)->create();

        $project->images()->save(factory(Image::class)->make([
            'path' => 'url',
        ]));

        $url = $project->getProjectImageUrl('medium');

        $this->assertEquals('/manager/images/medium/url', $url);
    }

    /**
     * Assert a collection is ordered.
     *
     * @param  \Illuminate\Support\Collection $ordered Collection of numbers.
     */
    protected function assertOrder(Collection $ordered)
    {
        $current = 0;

        $ordered->each(function ($order) use (&$current) {
            $this->assertTrue($order >= $current);

            if ($order != $current) {
                $current = $order;
            }
        });
    }

    /**
     * Make four projects for allVisible tests.
     */
    protected function makeProjectsForAllVisibleTest($makeVisible = true, $type = 'web')
    {
        foreach (range(0, 3) as $time) {
            factory(Project::class)->create();
        }

        $conditions = ['type' => $type];

        if ($makeVisible) {
            $conditions['visible'] = true;
        }

        Project::all()->each(function ($project) use ($conditions) {
            $this->user->updateProject($project, $conditions);
        });
    }
}
