<?php namespace ILIAS\GlobalScreen\MainMenu;

use ILIAS\GlobalScreen\MainMenu\Entry\EntryI;
use ILIAS\GlobalScreen\MainMenu\Slate\SlateInterfaceInterface;

/**
 * Interface StorageAdapterInterface
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
interface StorageAdapterInterface {

	/**
	 * @param EntryInterface $entry
	 *
	 * @return EntryInterface
	 */
	public function storeEntry(EntryI $entry): EntryI;


	/**
	 * @param EntryInterface $entry
	 *
	 * @return EntryInterface
	 */
	public function readEntry(EntryI $entry): EntryI;


	/**
	 * @param EntryInterface $entry
	 *
	 * @return EntryInterface
	 */
	public function updateEntry(EntryI $entry): EntryI;


	/**
	 * @param ISlate $slate
	 */
	public function storeSlate(SlateInterfaceInterface $slate);


	/**
	 * @param ISlate $slate
	 */
	public function readSlate(SlateInterfaceInterface $slate);


	/**
	 * @param ISlate $slate
	 */
	public function updateSlate(SlateInterfaceInterface $slate);
}