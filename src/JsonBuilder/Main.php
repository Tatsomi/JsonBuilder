<?php

declare(strict_types=1);

namespace JsonBuilder;

use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\World;

class Main extends PluginBase {

    /** @var array<int, array{x: int, y: int, z: int, id: string}> */
    private array $blocks = [];

    protected function onEnable(): void {
        $this->saveResource("data.json");
        $this->loadData();
    }

    private function loadData(): void {
        $filePath = $this->getDataFolder() . "data.json";

        if (!file_exists($filePath)) {
            $this->getLogger()->error("data.json not found at: " . $filePath);
            $this->blocks = [];
            return;
        }

        $config = new Config($filePath, Config::JSON);
        $this->blocks = $config->get("blocks", []);

        $this->getLogger()->info("Loaded " . count($this->blocks) . " block(s) from data.json.");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        return match ($command->getName()) {
            "refresh" => $this->handleRefresh($sender),
            "build"   => $this->handleBuild($sender),
            default   => false,
        };
    }

    private function handleRefresh(CommandSender $sender): bool {
        if (!$sender->hasPermission("jsonbuilder.command.refresh")) {
            $sender->sendMessage("§cYou don't have permission to use this command.");
            return true;
        }

        $this->loadData();
        $sender->sendMessage("§aJSON data reloaded successfully. " . count($this->blocks) . " block(s) loaded.");
        return true;
    }

    private function handleBuild(CommandSender $sender): bool {
        if (!$sender->hasPermission("jsonbuilder.command.build")) {
            $sender->sendMessage("§cYou don't have permission to use this command.");
            return true;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage("§cThis command can only be used in-game.");
            return true;
        }

        if (empty($this->blocks)) {
            $sender->sendMessage("§cNo blocks loaded. Use /refresh first or check data.json.");
            return true;
        }

        $world = $sender->getWorld();
        $origin = $sender->getPosition()->floor();

        $built = 0;
        $failed = 0;

        foreach ($this->blocks as $entry) {
            if (!isset($entry['x'], $entry['y'], $entry['z'], $entry['id'])) {
                $failed++;
                continue;
            }

            $x = $origin->getX() + (int)$entry['x'];
            $y = $origin->getY() + (int)$entry['y'];
            $z = $origin->getZ() + (int)$entry['z'];

            if ($y < World::Y_MIN || $y > World::Y_MAX) {
                $failed++;
                continue;
            }

            $item = StringToItemParser::getInstance()->parse($entry['id']);
            if ($item === null) {
                $failed++;
                continue;
            }

            $block = $item->getBlock();
            if (!$block instanceof Block) {
                $failed++;
                continue;
            }

            $pos = new \pocketmine\math\Vector3($x, $y, $z);
            $world->setBlock($pos, $block, false);
            $built++;
        }

        $sender->sendMessage("§aBuild complete: §e{$built} §ablock(s) placed, §c{$failed} §afailed.");
        return true;
    }
}
