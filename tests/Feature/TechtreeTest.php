<?php

namespace Feature;

use OGame\Services\ObjectService;
use Tests\AccountTestCase;

/**
 * Test that the tech tree works as expected.
 */
class TechtreeTest extends AccountTestCase
{
    /**
     * Verify that techtree techinfo popups for all objects return HTTP 200.
     */
    public function testTechtreeInfoPopups(): void
    {
        // Get all objects
        $objectService = new ObjectService();

        foreach ($objectService->getObjects() as $object) {
            $response = $this->get('ajax/techtree?tab=2&object_id=' . $object->id);

            try {
                $response->assertStatus(200);
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail('AJAX techtree info page for "' . $object->title . '" does not return HTTP 200.');
            }
        }
    }

    /**
     * Verify that techtree applications popups for all objects return HTTP 200.
     */
    public function testTechtreeApplicationsPopupsHttp200(): void
    {
        // Get all objects
        $objectService = new ObjectService();

        foreach ($objectService->getObjects() as $object) {
            $response = $this->get('ajax/techtree?tab=4&object_id=' . $object->id);

            try {
                $response->assertStatus(200);
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail('AJAX techtree applications page for "' . $object->title . '" does not return HTTP 200.');
            }
        }
    }

    /**
     * Verify that techtree applications popups for all objects return HTTP 200.
     */
    public function testTechtreeApplicationsPopupsLogic(): void
    {
        // User/planet without any levels/prerequisites.
        $objectService = new ObjectService();
        $object = $objectService->getObjectByMachineName('laser_technology');

        $response = $this->get('ajax/techtree?tab=4&object_id=' . $object->id);
        // Assert that no prerequisites are met for any of the applications.
        $response->assertSee('data-prerequisites-met="false"', false);
        $response->assertDontSee('data-prerequisites-met="true"', false);

        // User/planet with all levels/prerequisites for laser technology applications.
        $this->planetSetObjectLevel('research_lab', 12);
        $this->planetSetObjectLevel('shipyard', 12);
        $this->playerSetResearchLevel('laser_technology', 12);
        $this->playerSetResearchLevel('energy_technology', 12);
        $this->playerSetResearchLevel('ion_technology', 12);
        $this->playerSetResearchLevel('hyperspace_technology', 8);
        $this->playerSetResearchLevel('shielding_technology', 8);

        $response = $this->get('ajax/techtree?tab=4&object_id=' . $object->id);
        $content = $response->getContent();
        if ($content === false) {
            $this->fail('AJAX techtree applications page for "' . $object->title . '" does not return any content.');
        }
        $metCount = substr_count($content, 'data-prerequisites-met="true"');
        $notMetCount = substr_count($content, 'data-prerequisites-met="false"');

        // Assert that 5 applications are met and 1 is not.
        $this->assertEquals(5, $metCount);
        $this->assertEquals(1, $notMetCount);

        // Set hyperspace drive to level 5 so the battlecruiser application is also met.
        $this->playerSetResearchLevel('hyperspace_drive', 5);
        $response = $this->get('ajax/techtree?tab=4&object_id=' . $object->id);

        // Assert that all prerequisites are now met for all applications.
        $response->assertDontSee('data-prerequisites-met="false"', false);
        $response->assertSee('data-prerequisites-met="true"', false);
    }
}
