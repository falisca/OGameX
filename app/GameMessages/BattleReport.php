<?php

namespace OGame\GameMessages;

use OGame\Facades\AppUtil;
use OGame\GameMessages\Abstracts\GameMessage;
use OGame\GameMissions\BattleEngine\BattleResultRound;
use OGame\GameObjects\Models\Units\UnitCollection;
use OGame\Models\Planet\Coordinate;
use OGame\Models\Resources;

class BattleReport extends GameMessage
{
    /**
     * @var \OGame\Models\BattleReport|null The battle report model from database.
     */
    private \OGame\Models\BattleReport|null $battleReportModel = null;

    protected function initialize(): void
    {
        $this->key = 'battle_report';
        $this->params = [];
        $this->tab = 'fleets';
        $this->subtab = 'combat_reports';
    }

    /**
     * Load battle report model from database. If already loaded, do nothing.
     *
     * @return void
     */
    private function loadBattleReportModel(): void
    {
        if ($this->battleReportModel !== null) {
            // Already loaded.
            return;
        }

        // Load battle report model from database associated with the message.
        $battleReport = \OGame\Models\BattleReport::where('id', $this->message->battle_report_id)->first();
        if ($battleReport === null) {
            // If battle report is not found, we use an empty model. This is for testing purposes.
            $this->battleReportModel = new \OGame\Models\BattleReport();
        } else {
            $this->battleReportModel = $battleReport;
        }
    }

    /**
     * @inheritdoc
     */
    public function getSubject(): string
    {
        $this->loadBattleReportModel();

        // Load the planet name from the references table and return the subject filled with the planet name.
        $coordinate = new Coordinate($this->battleReportModel->planet_galaxy, $this->battleReportModel->planet_system, $this->battleReportModel->planet_position);
        $planet = $this->planetServiceFactory->makeForCoordinate($coordinate);
        if ($planet) {
            $subject = __('Combat report :planet', ['planet' => '[planet]' . $planet->getPlanetId() . '[/planet]']);
        } else {
            $subject = __('Combat report  :planet', ['planet' => '[coordinates]' . $coordinate->asString() . '[/coordinates]']);
        }

        return $this->replacePlaceholders($subject);
    }

    /**
     * @inheritdoc
     */
    public function getBody(): string
    {
        $params = $this->getBattleReportParams();
        return view('ingame.messages.templates.battle_report', $params)->render();
    }

    /**
     * @inheritdoc
     */
    public function getBodyFull(): string
    {
        $params = $this->getBattleReportParams();
        return view('ingame.messages.templates.battle_report_full', $params)->render();
    }

    /**
     * @inheritdoc
     */
    public function getFooterDetails(): string
    {
        // Show more details link in the footer of the espionage report.
        return ' <a class="fright txt_link msg_action_link overlay"
                   href="' . route('messages.ajax.getmessage', ['messageId' => $this->message->id])  .'"
                   data-overlay-title="More details">
                    More details
                </a>';
    }

    /**
     * Get the battle report params.
     *
     * @return array<string, mixed>
     */
    private function getBattleReportParams(): array
    {
        $this->loadBattleReportModel();

        // TODO: add feature test for code below and check edgecases, such as when the planet has been deleted and
        // does not exist anymore. What should we show in that case?

        // Load planet by coordinate.
        $coordinate = new Coordinate($this->battleReportModel->planet_galaxy, $this->battleReportModel->planet_system, $this->battleReportModel->planet_position);
        $planet = $this->planetServiceFactory->makeForCoordinate($coordinate);

        // If planet owner is the same as the player, we load the player by planet owner which is already loaded.
        if ($this->battleReportModel->planet_user_id === $planet->getPlayer()->getId()) {
            $defender = $this->playerServiceFactory->make($planet->getPlayer()->getId());
        } else {
            // It is theoretically possible that the original player has deleted their planet and another user has
            // colonized the same position of the original planet. In that case, we should load the player by user_id
            // from the espionage report.
            $defender = $this->playerServiceFactory->make($this->battleReportModel->planet_user_id);
        }
        $defender_weapons = $this->battleReportModel->defender['weapon_technology'] * 10;
        $defender_shields = $this->battleReportModel->defender['shielding_technology'] * 10;
        $defender_armor = $this->battleReportModel->defender['armor_technology'] * 10;

        // Extract params from the battle report model.
        $attackerPlayerId = $this->battleReportModel->attacker['player_id'];
        $attackerLosses = $this->battleReportModel->attacker['resource_loss'];
        $defenderLosses = $this->battleReportModel->defender['resource_loss'];

        $lootPercentage = $this->battleReportModel->loot['percentage'];
        $lootMetal = $this->battleReportModel->loot['metal'];
        $lootCrystal = $this->battleReportModel->loot['crystal'];
        $lootDeuterium = $this->battleReportModel->loot['deuterium'];
        $lootResources = new Resources($lootMetal, $lootCrystal, $lootDeuterium, 0);

        $debrisMetal = $this->battleReportModel->debris['metal'];
        $debrisCrystal = $this->battleReportModel->debris['crystal'];
        $debrisResources = new Resources($debrisMetal, $debrisCrystal, 0, 0);

        $repairedDefensesCount = 0;
        if (!empty($this->battleReportModel->repaired_defenses)) {
            foreach ($this->battleReportModel->repaired_defenses as $defense_key => $defense_count) {
                $repairedDefensesCount += $defense_count;
            }
        }

        // Load attacker player
        // TODO: add unit test for attacker/defender research levels.
        $attacker = $this->playerServiceFactory->make($attackerPlayerId, true);
        $attacker_weapons = $this->battleReportModel->attacker['weapon_technology'] * 10;
        $attacker_shields = $this->battleReportModel->attacker['shielding_technology'] * 10;
        $attacker_armor = $this->battleReportModel->attacker['armor_technology'] * 10;

        $attacker_units = new UnitCollection();
        foreach ($this->battleReportModel->attacker['units'] as $machine_name => $amount) {
            $attacker_units->addUnit($this->objects->getUnitObjectByMachineName($machine_name), $amount);
        }

        $defender_units = new UnitCollection();
        foreach ($this->battleReportModel->defender['units'] as $machine_name => $amount) {
            $defender_units->addUnit($this->objects->getUnitObjectByMachineName($machine_name), $amount);
        }

        // Load rounds and cast to battle result round object.
        $rounds = [];
        if ($this->battleReportModel->rounds !== null) {
            foreach ($this->battleReportModel->rounds as $round) {
                $obj = new BattleResultRound();
                $obj->fullStrengthAttacker = $round['full_strength_attacker'];
                $obj->fullStrengthDefender = $round['full_strength_defender'];
                $obj->absorbedDamageAttacker = $round['absorbed_damage_attacker'];
                $obj->absorbedDamageDefender = $round['absorbed_damage_defender'];
                $obj->hitsAttacker = $round['hits_attacker'];
                $obj->hitsDefender = $round['hits_defender'];
                $obj->defenderShips = new UnitCollection();
                foreach ($round['defender_ships'] as $machine_name => $amount) {
                    $unit = $this->objects->getUnitObjectByMachineName($machine_name);
                    $obj->defenderShips->addUnit($unit, $amount);
                }
                $obj->attackerShips = new UnitCollection();
                foreach ($round['attacker_ships'] as $machine_name => $amount) {
                    $unit = $this->objects->getUnitObjectByMachineName($machine_name);
                    $obj->attackerShips->addUnit($unit, $amount);
                }
                $obj->defenderLosses = new UnitCollection();
                foreach ($round['defender_losses'] as $machine_name => $amount) {
                    $unit = $this->objects->getUnitObjectByMachineName($machine_name);
                    $obj->defenderLosses->addUnit($unit, $amount);
                }
                $obj->attackerLosses = new UnitCollection();
                foreach ($round['attacker_losses'] as $machine_name => $amount) {
                    $unit = $this->objects->getUnitObjectByMachineName($machine_name);
                    $obj->attackerLosses->addUnit($unit, $amount);
                }
                $obj->defenderLossesInThisRound = new UnitCollection();
                foreach ($round['defender_losses_in_this_round'] as $machine_name => $amount) {
                    $unit = $this->objects->getUnitObjectByMachineName($machine_name);
                    $obj->defenderLossesInThisRound->addUnit($unit, $amount);
                }
                $obj->attackerLossesInThisRound = new UnitCollection();
                foreach ($round['attacker_losses_in_this_round'] as $machine_name => $amount) {
                    $unit = $this->objects->getUnitObjectByMachineName($machine_name);
                    $obj->attackerLossesInThisRound->addUnit($unit, $amount);
                }
                $rounds[] = $obj;
            }
        }

        // Determine if attacker or defender won the battle or if it was a draw.
        // We do this based on last round result.
        if (count($rounds) === 0) {
            // No rounds, attacker wins.
            $winner = 'attacker';
        } else {
            $lastRound = $rounds[count($rounds) - 1];
            if ($lastRound->attackerShips->getAmount() > 0 && $lastRound->defenderShips->getAmount() > 0) {
                // Both players have ships left, draw.
                $winner = 'draw';
            } elseif ($lastRound->attackerShips->getAmount() > 0) {
                // Attacker has ships left, attacker wins.
                $winner = 'attacker';
            } else {
                // Defender has ships left, defender wins.
                $winner = 'defender';
            }
        }

        return [
            'subject' => $this->getSubject(),
            'from' => $this->getFrom(),
            'attacker_name' => $attacker->getUsername(false),
            'defender_name' => $defender->getUsername(false),
            'attacker_class' => ($winner === 'attacker') ? 'undermark' : (($winner === 'draw') ? 'middlemark' : 'overmark'),
            'defender_class' => ($winner === 'defender') ? 'undermark' : (($winner === 'draw') ? 'middlemark' : 'overmark'),
            'defender_planet_name' => $planet->getPlanetName(),
            'defender_planet_coords' => $planet->getPlanetCoordinates()->asString(),
            'defender_planet_link' => route('galaxy.index', ['galaxy' => $planet->getPlanetCoordinates()->galaxy, 'system' => $planet->getPlanetCoordinates()->system, 'position' => $planet->getPlanetCoordinates()->position]),
            'attacker_losses' => AppUtil::formatNumberLong($attackerLosses),
            'defender_losses' => AppUtil::formatNumberLong($defenderLosses),
            'loot' => AppUtil::formatNumberShort($lootResources->sum()),
            'loot_resources' => $lootResources,
            'loot_percentage' => $lootPercentage,
            'debris' => AppUtil::formatNumberShort($debrisResources->sum()),
            'repaired_defenses_count' => $repairedDefensesCount,
            'attacker_weapons' => $attacker_weapons,
            'attacker_shields' => $attacker_shields,
            'attacker_armor' => $attacker_armor,
            'defender_weapons' => $defender_weapons,
            'defender_shields' => $defender_shields,
            'defender_armor' => $defender_armor,
            'military_objects' => $this->objects->getMilitaryShipObjects(),
            'civil_objects' => $this->objects->getCivilShipObjects(),
            'defense_objects' => $this->objects->getDefenseObjects(),
            'attacker_units_start' => $attacker_units,
            'defender_units_start' => $defender_units,
            'rounds' => $rounds,
        ];
    }
}