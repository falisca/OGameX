<?php

namespace OGame\GameMissions\BattleEngine;

use OGame\GameObjects\Models\UnitObject;

/**
 * Model class that represents a unit in a battle keeping track of its health and other properties.
 */
class BattleUnit
{
    /**
     * @var UnitObject The unit object that this battle unit represents.
     */
    public UnitObject $unitObject;

    /**
     * @var int The original hull plating of the unit. This is the structural integrity of the unit divided by 10.
     */
    public int $originalHullPlating;

    /**
     * @var int The original shield points of the unit.
     */
    public int $originalShieldPoints;

    /**
     * @var int The original attack power of the unit.
     */
    public int $originalAttackPower;

    /**
     * @var int The current health points of the unit. Hull plating = structural integrity / 10.
     */
    public int $currentHullPlating;

    /**
     * @var int The shield points of the unit.
     *
     * Damage is first applied to the shield, then to the hull plating. After every round of combat, the shield regenerates.
     */
    public int $currentShieldPoints;

    /**
     * @var int The attack power of the unit. This is the amount of damage the unit deals to another unit in a single round of combat.
     */
    public int $currentAttackPower;

    /**
     * Create a new BattleUnit object.
     *
     * @param UnitObject $unitObject
     * @param int $hullPlating
     * @param int $shieldPoints
     * @param int $attackPower
     */
    function __construct(UnitObject $unitObject, int $hullPlating, int $shieldPoints, int $attackPower)
    {
        $this->unitObject = $unitObject;
        $this->originalHullPlating = $hullPlating;
        $this->originalShieldPoints = $shieldPoints;
        $this->originalAttackPower = $attackPower;
        $this->currentHullPlating = $hullPlating;
        $this->currentShieldPoints = $shieldPoints;
        $this->currentAttackPower = $attackPower;
    }
}