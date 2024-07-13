<?php

/**
 * View for members module.
 * @author matti
 *
 */
class MitspielerView extends CrudRefLocationView {

	/**
	 * Create the start view.
	 */
	function __construct($ctrl) {
		$this->setController($ctrl);
	}
	
	function start() {
		?>
		<div class="row px-2">
		<?php
		if($this->getData()->getSysdata()->getUsersContact() == "") return;
		$members = $this->getData()->getMembers();
		$customFields = $this->getData()->getCustomFields('c', true);

		$table = new Table($members);
		$table->removeColumn("id");
		$table->showFilter(true);
		$table->write();

		?>
		</div>
		<?php
	}
	
	function startOptions() {
		// none
	}
}

?>