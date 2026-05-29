<?php

declare(strict_types=1);

namespace RenamingUI;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\item\Item;
use pocketmine\player\Player;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends PluginBase{

    private Config $config;

    public function onEnable() : void{
        @mkdir($this->getDataFolder());

        $this->saveDefaultConfig();
        $this->config = $this->getConfig();

        if(!$this->getServer()->getPluginManager()->getPlugin("FormAPI")){
            $this->getLogger()->error("FormAPI by jojoe77777 is required!");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->getLogger()->info("RenamingUI Enabled!");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{

        if(!$sender instanceof Player){
            $sender->sendMessage("Use this command in-game.");
            return true;
        }

        switch(strtolower($command->getName())){
            case "renameui":
            case "rename":
                $this->openItemSelector($sender);
                return true;
        }

        return false;
    }

    private function openItemSelector(Player $player) : void{

        $items = [];
        $dropdown = [];

        $inventory = $player->getInventory();

        for($i = 0; $i < $inventory->getSize(); $i++){
            $item = $inventory->getItem($i);

            if(!$item->isNull()){
                $items[] = [
                    "slot" => $i,
                    "item" => $item
                ];

                $dropdown[] = $item->getName() . " x" . $item->getCount();
            }
        }

        if(count($items) <= 0){
            $player->sendMessage($this->color($this->config->get("no-items-message")));
            return;
        }

        $form = new CustomForm(function(Player $player, ?array $data) use ($items) : void{

            if($data === null){
                $player->sendMessage($this->color($this->config->get("cancel-message")));
                return;
            }

            $selected = $items[$data[1]] ?? null;

            if($selected === null){
                return;
            }

            $slot = $selected["slot"];
            $item = $selected["item"];

            $this->openRenameForm($player, $slot, $item);
        });

        $form->setTitle($this->color($this->config->get("select-form-title")));
        $form->addLabel($this->color($this->config->get("select-form-message")));
        $form->addDropdown(
            $this->color($this->config->get("dropdown-message")),
            $dropdown
        );

        $player->sendForm($form);
    }

    private function openRenameForm(Player $player, int $slot, Item $item) : void{

        $form = new CustomForm(function(Player $player, ?array $data) use ($slot, $item) : void{

            if($data === null){
                $player->sendMessage($this->color($this->config->get("cancel-message")));
                return;
            }

            $newName = trim((string)$data[1]);

            if($newName === ""){
                $player->sendMessage($this->color($this->config->get("empty-name-message")));
                return;
            }

            $newName = $this->color($newName);

            $this->openConfirmForm($player, $slot, $item, $newName);
        });

        $form->setTitle($this->color($this->config->get("rename-form-title")));
        $form->addLabel(str_replace(
            "{item}",
            $item->getName(),
            $this->color($this->config->get("rename-form-message"))
        ));

        $form->addInput(
            $this->color($this->config->get("rename-input-message")),
            "&cEpic Sword"
        );

        $player->sendForm($form);
    }

    private function openConfirmForm(Player $player, int $slot, Item $item, string $newName) : void{

        $cost = (int)$this->config->get("xp-cost");

        $bypass = $player->hasPermission("renamingui.bypass");

        $form = new SimpleForm(function(Player $player, ?int $data) use ($slot, $item, $newName, $cost, $bypass) : void{

            if($data === null){
                $player->sendMessage($this->color($this->config->get("cancel-message")));
                return;
            }

            switch($data){

                case 0:

                    if(!$bypass){

                        if($player->getXpManager()->getXpLevel() < $cost){
                            $msg = str_replace(
                                "{xp}",
                                (string)$cost,
                                $this->config->get("not-enough-xp-message")
                            );

                            $player->sendMessage($this->color($msg));
                            return;
                        }

                        $player->getXpManager()->subtractXpLevels($cost);
                    }

                    $inventory = $player->getInventory();

                    $currentItem = $inventory->getItem($slot);

                    if($currentItem->isNull()){
                        $player->sendMessage($this->color($this->config->get("item-missing-message")));
                        return;
                    }

                    /*
                     * KEEP ENCHANTS + EFFECTS
                     * We only change custom name
                     */
                    $currentItem->setCustomName($newName);

                    $inventory->setItem($slot, $currentItem);

                    $msg = str_replace(
                        ["{item}", "{name}"],
                        [$currentItem->getName(), $newName],
                        $this->config->get("rename-success-message")
                    );

                    $player->sendMessage($this->color($msg));

                    break;

                case 1:
                    $player->sendMessage($this->color($this->config->get("cancel-message")));
                    break;

                case 2:
                    $player->sendMessage($this->color($this->config->get("close-message")));
                    break;
            }
        });

        $title = $this->config->get("confirm-form-title");

        if($bypass){
            $title .= "\n" . $this->config->get("free-rename-message");
        }

        $form->setTitle($this->color($title));

        $message = str_replace(
            ["{xp}", "{item}", "{name}"],
            [
                (string)$cost,
                $item->getName(),
                $newName
            ],
            $this->config->get("confirm-message")
        );

        if($bypass){
            $message .= "\n" . $this->config->get("free-confirm-message");
        }

        $form->setContent($this->color($message));

        $form->addButton($this->color($this->config->get("confirm-button")));
        $form->addButton($this->color($this->config->get("cancel-button")));
        $form->addButton($this->color($this->config->get("close-button")));

        $player->sendForm($form);
    }

    private function color(string $text) : string{
        return TextFormat::colorize($text);
    }
}
