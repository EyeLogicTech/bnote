<?php

/**
 * View for members module. hL mit Profilbild
 */
class MitspielerView extends CrudRefLocationView {

	function __construct($ctrl) {
		$this->setController($ctrl);
	}
	
	function start() {
		global $system_data;
		?>
		<p class="membercard_hint"><?php echo Lang::txt("MitspielerView_start.message"); ?></p>
		<div class="row px-2">
		<?php
		if ($this->getData()->getSysdata()->getUsersContact() == "") return;
		$members = $this->getData()->getMembers();
		$customFields = $this->getData()->getCustomFields('c', true);
		
		for ($i = 1; $i < count($members); $i++) {
			$member = $members[$i];
			?>
			<div class="card col-md-3 mb-2 me-2 membercard">
				<div class="card-body p-2">
				
					<h5 class="card-title mb-2">
						<?php
						echo $member["fullname"];
						if ($member["nickname"] != "") {
							echo " (" . $member["nickname"] . ")";
						}
						?>
					</h5>

					<div class="membercard_photo mb-2">
						<?php echo $this->renderProfileImage($member); ?>
					</div>
					
					<div class="membercard_instrument"><?php
					echo $member["instrumentname"];
					if ($member["birthday"] != "0000-00-00") {
						echo " | " . Data::convertDateFromDb($member["birthday"]);
					}
					?></div>
					
					<div class="membercard_phone"><?php
					$showPhone = false;
					if ($member["phone"] != "") {
						echo "<a href=\"tel:" . $member["phone"] . "\">" . $member["phone"] . "</a>";
						$showPhone = true;
					} 
					$showMobile = false;
					if ($member["mobile"] != "") {
						if ($showPhone) {
							echo " | ";
						}
						echo "<a href=\"tel:" . $member["mobile"] . "\">" . $member["mobile"] . "</a>";
						$showMobile = true;
					}
					if (!$showMobile && !$showPhone) {
						echo "&nbsp;";
					}
					?></div>
					
					<div class="membercard_web"><?php 
					echo "<a href=\"mailto:" . $member["email"] . "\">" . $member["email"] . "</a>";
					if ($member["web"] != "") {
						$webHref = Data::startsWith($member["web"], "http") ? $member["web"] : "http://" . $member["web"];
						echo " | <a href=\"" . $webHref . "\" target=\"_blank\">" . $member["web"] . "</a>";
					}
					?></div>
					
					<div class="membercard_address"><?php
					echo $this->formatAddress($member, FALSE); 
					?></div>
					
					<div class="membercard_customfields">
					<?php
					$entries = array();
					foreach ($customFields as $j => $field) {
						if ($j == 0) continue;
						$val = "";
						if (isset($member[$field["techname"]])) {
							$val = $member[$field["techname"]];
							if ($field["fieldtype"] == "BOOLEAN") {
								$val = $val == "" || $val == "0" ? "nein" : "ja";
							} else if ($field["fieldtype"] == "DOUBLE") {
								$val = Data::convertFromDb($val);
							}
						}
						if ($val != "") {
							$entries[] = $field["txtdefsingle"] . ": " . $val;
						}
					}
					echo join(" | ", $entries);
					?>
					</div>

				</div>
			</div>
			<?php
		}
		?>
		</div>
		<?php
	}
	
	function startOptions() {
		// none
	}
	
	private function renderProfileImage($member) {
		global $system_data;

		$contactId = $member["id"];
		$imgPath = $system_data->getProfilePicturePath() . $contactId . ".jpg";

		if (file_exists($imgPath)) {
			$mtime = filemtime($imgPath);
			$imgUrl = $system_data->getProfilePictureUrl($contactId) . "?t=" . $mtime;
			$alt = htmlspecialchars("Profilbild von " . $member["fullname"]);

			return "<img src=\"$imgUrl\" alt=\"$alt\" class=\"membercard_photo mb-2\" style=\"width: 125px; height: auto; border-radius: 5px; display: block; margin: 0 auto;\" />";
		}

		return "";
	}
}

?>