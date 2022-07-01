<?php

/**
 *  ███╗   ███╗██╗███╗   ██╗███████╗██████╗ ██╗    ██╗ █████╗ ██████╗ ███████╗
 *  ████╗ ████║██║████╗  ██║██╔════╝██╔══██╗██║    ██║██╔══██╗██╔══██╗██╔════╝
 *  ██╔████╔██║██║██╔██╗ ██║█████╗  ██████╔╝██║ █╗ ██║███████║██████╔╝█████╗
 *  ██║╚██╔╝██║██║██║╚██╗██║██╔══╝  ██╔══██╗██║███╗██║██╔══██║██╔══██╗██╔══╝
 *  ██║ ╚═╝ ██║██║██║ ╚████║███████╗██║  ██║╚███╔███╔╝██║  ██║██║  ██║███████╗
 *  ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
 *
 * A game written in PHP for PocketMine-MP software.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Copyright 2022 © LatamPMDevs
 */

declare(strict_types=1);

namespace LatamPMDevs\minerware\arena;

use LatamPMDevs\minerware\database\DataHolder;
use LatamPMDevs\minerware\Minerware;
use LatamPMDevs\minerware\utils\Utils;
use pocketmine\math\Vector3;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\World;
use ZipArchive;
use function mkdir;

final class Map {

	public static array $maps = [];

	public const PLATFORM_X_SIZE = 24;
	public const PLATFORM_Z_SIZE = 24;

	public const MINI_PLATFORMS = [// Referenced with min position
		[[3, 1, 3], [4, 1, 3], [3, 1, 4], [4, 1, 4]],
		[[11, 1, 3], [12, 1, 3], [11, 1, 4], [12, 1, 4]],
		[[19, 1, 3], [20, 1, 3], [19, 1, 4], [20, 1, 4]],
		[[3, 1, 11], [4, 1, 11], [3, 1, 12], [4, 1, 12]],
		[[11, 1, 11], [12, 1, 11], [11, 1, 12], [12, 1, 12]],
		[[19, 1, 11], [20, 1, 11], [19, 1, 12], [20, 1, 12]],
		[[3, 1, 19], [4, 1, 19], [3, 1, 20], [4, 1, 20]],
		[[11, 1, 19], [12, 1, 19], [11, 1, 20], [12, 1, 20]],
		[[19, 1, 19], [20, 1, 19], [19, 1, 20], [20, 1, 20]]

	];

	private string $name;

	private Vector3 $platformMinPos;

	private Vector3 $platformMaxPos;

	private Vector3 $center;

	/** @var Vector3[] */
	private array $spawns = [];

	private Vector3 $winnersCage;

	private Vector3 $losersCage;

	public static function getByName(string $name) : ?self {
		foreach (self::$maps as $map) {
			if ($map->getName() === $name) {
				return $map;
			}
		}

		return null;
	}

	public function __construct(private DataHolder $data) {
		$this->name = $data->getString("name");

		$platform = $data->getArray("platform");
		$minMax = Utils::calculateMinAndMaxPos(
			new Vector3($platform["pos1"]["X"], $platform["pos1"]["Y"], $platform["pos1"]["Z"]),
			new Vector3($platform["pos2"]["X"], $platform["pos2"]["Y"], $platform["pos2"]["Z"])
		);
		$this->platformMinPos = $minMax[0];
		$this->platformMaxPos = $minMax[1];
		$this->center = $this->platformMinPos->add(self::PLATFORM_X_SIZE / 2, 0, self::PLATFORM_Z_SIZE / 2);

		foreach ($data->getArray("spawns") as $spawnData) {
			$this->spawns[] = new Vector3($spawnData["X"], $spawnData["Y"], $spawnData["Z"]);
		}
		$cages = $data->getArray("cages");
		$this->winnersCage = new Vector3($cages["winners"]["X"], $cages["winners"]["Y"], $cages["winners"]["Z"]);
		$this->losersCage = new Vector3($cages["losers"]["X"], $cages["losers"]["Y"], $cages["losers"]["Z"]);

		self::$maps[] = $this;
	}

	public function getName() : string {
		return $this->name;
	}

	public function getPlatformMinPos() : Vector3 {
		return $this->platformMinPos;
	}

	public function getPlatformMaxPos() : Vector3 {
		return $this->platformMaxPos;
	}

	public function getCenter() : Vector3 {
		return $this->center;
	}

	/**
	 * @return Vector3[]
	 */
	public function getSpawns() : array {
		return $this->spawns;
	}

	public function getWinnersCage() : Vector3 {
		return $this->winnersCage;
	}

	public function getLosersCage() : Vector3 {
		return $this->losersCage;
	}

	public function getData() : DataHolder {
		return $this->data;
	}

	public function getZip() : string {
		return Minerware::getInstance()->getDataFolder() . "database" . DIRECTORY_SEPARATOR . "backups" . DIRECTORY_SEPARATOR . $this->name . ".zip";
	}

	public function generateWorld(string $uniqueId) : World {
		$worldPath = Minerware::getInstance()->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $this->name . "-" . $uniqueId . DIRECTORY_SEPARATOR;

		# Create files
		@mkdir($worldPath);
		$backup = $this->getZip();
		$zip = new ZipArchive();
		$zip->open($backup);
		$zip->extractTo($worldPath);
		$zip->close();

		#Get World
		if (Minerware::getInstance()->getServer()->getWorldManager()->loadWorld($this->name . "-" . $uniqueId)) {
			return Minerware::getInstance()->getServer()->getWorldManager()->getWorldByName($this->name . "-" . $uniqueId);
		}

		throw new AssumptionFailedError("Error Generating world");
	}
}
