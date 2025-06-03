<?php

/**
 * View to manage the user's personal data. hL: Profilbilder
 */
class KontaktdatenView extends CrudRefLocationView {

	function __construct($ctrl) {
		$this->setController($ctrl);
	}

	function start($uploadErrorMessage = "") {
		global $system_data;

		$contact = $this->getData()->getContactForUser($this->getUserId());
		if ($contact <= 0) {
			Writing::p(Lang::txt("KontaktdatenView_start.message"));
			return;
		}

		$userid = $this->getUserId();
		$contactid = $this->getData()->getContactIdForUser($userid);
		$zielverzeichnis = $system_data->getProfilePicturePath();
		if (!is_dir($zielverzeichnis)) {
			mkdir($zielverzeichnis, 0755, true);
		}
		$bildDateiname = $contactid . ".jpg";
		$zieldatei = $zielverzeichnis . $bildDateiname;

		$bildUrl = $system_data->getProfilePictureUrl($contactid) . "?t=" . time();

		if (!empty($uploadErrorMessage)) {
			Writing::p('<div style="color: red; font-weight: bold; margin-bottom: 10px;">' . htmlspecialchars($uploadErrorMessage) . '</div>');
		}

		$html = '<div style="margin-bottom: 20px;">';
		$html .= '<label style="display:block; font-weight:bold; margin-bottom:5px;">Aktuelles Profilbild:</label>';

		if (file_exists($zieldatei)) {
			$html .= '<div style="display: flex; align-items: flex-start; gap: 20px;">';
			$html .= '<img src="' . $bildUrl . '" alt="Profilbild"
				style="width:100px; height:auto; border-radius:6px; box-shadow:0 0 4px rgba(0,0,0,0.2);">';
			$html .= '<form method="post" action="' . $this->modePrefix() . 'deleteProfilePicture" onsubmit="return confirm(\'Das Profilbild wird gelöscht.\');">
				<button type="submit">Löschen</button>
			</form>';
			$html .= '</div>';
		} else {
			$html .= '<form method="post" action="' . $this->modePrefix() . 'uploadProfilePicture" enctype="multipart/form-data" style="margin-top:10px;" onsubmit="return validateUploadForm();">
				<input type="file" name="foto" id="fotoInput" accept=".jpg,.jpeg" required>
				<button type="submit" id="uploadButton" disabled>Speichern</button>
			</form>
			<script>
				document.getElementById("fotoInput").addEventListener("change", function () {
					const file = this.files[0];
					document.getElementById("uploadButton").disabled = !file;
				});

				function validateUploadForm() {
					const fileInput = document.getElementById("fotoInput");
					const file = fileInput.files[0];
					if (!file) {
						alert("Bitte wählen Sie eine Datei aus.");
						return false;
					}
					const allowedTypes = ["image/jpeg"];
					if (!allowedTypes.includes(file.type)) {
						alert("Fehler – die Datei wurde nicht gespeichert. Nur JPEG-Dateien sind erlaubt.");
						return false;
					}
					return true;
				}
			</script>';
		}

		$html .= '</div>';
		$html .= '<hr style="margin: 10px 0;">';
		Writing::p($html);

		$form = new Form(Lang::txt("KontaktdatenView_start.Form"), $this->modePrefix() . "savePD");

		$form->addElement(Lang::txt("KontakteData_construct.name"), new Field("name", $contact["name"], FieldType::CHAR), true, 4);
		$form->addElement(Lang::txt("KontakteData_construct.surname"), new Field("surname", $contact["surname"], FieldType::CHAR), true, 4);
		$form->addElement(Lang::txt("KontakteData_construct.nickname"), new Field("nickname", $contact["nickname"], FieldType::CHAR), false, 4);
		$form->addElement(Lang::txt("KontakteData_construct.email"), new Field("email", $contact["email"], FieldType::EMAIL), true, 4);
		$form->addElement(Lang::txt("KontakteData_construct.birthday"), new Field("birthday", $contact["birthday"], FieldType::DATE), false, 4);
		$form->addElement(Lang::txt("KontakteData_construct.instrument"), new Dropdown("instrument"), true, 4);
		$form->setForeign(Lang::txt("KontakteData_construct.instrument"), "instrument", "id", "name", $contact["instrument"], false, true);

		$address = $this->getData()->getAddress($contact["address"]);
		$this->addAddressFieldsToForm($form, $address);
		$form->addElement("Mobiltelefon", new Field("mobile", $contact["mobile"], FieldType::CHAR), false, 3);

		$this->appendCustomFieldsToForm($form, 'c', $contact, true);

		$form->addElement(Lang::txt("KontaktdatenView_start.share_email"), new Field("share_email", $contact["share_email"], FieldType::BOOLEAN), false, 12);
		$form->addElement(Lang::txt("KontaktdatenView_start.share_address"), new Field("share_address", $contact["share_address"], FieldType::BOOLEAN), false, 12);
		$form->addElement(Lang::txt("KontaktdatenView_start.share_phones"), new Field("share_phones", $contact["share_phones"], FieldType::BOOLEAN), false, 12);
		$form->addElement(Lang::txt("KontaktdatenView_start.share_birthday"), new Field("share_birthday", $contact["share_birthday"], FieldType::BOOLEAN), false, 12);

		$form->write();
	}

	function savePD() {
		$this->getData()->update($this->getUserId(), $_POST);
		new Message("Daten gespeichert", "Die Änderungen wurden gespeichert.");
	}

	function uploadProfilePicture() {
		global $system_data;

		$errorMessage = "";
		$userid = $this->getUserId();
		$contactid = $this->getData()->getContactIdForUser($userid);

		if (
			isset($_FILES["foto"]) &&
			is_uploaded_file($_FILES["foto"]["tmp_name"]) &&
			$_FILES["foto"]["error"] === UPLOAD_ERR_OK
		) {
			if (mime_content_type($_FILES["foto"]["tmp_name"]) === "image/jpeg") {
				$originalPfad = $_FILES["foto"]["tmp_name"];
				$zielverzeichnis = $system_data->getProfilePicturePath();
				$zieldateiUser = $zielverzeichnis . $contactid . ".jpg";

				$erfolg = $this->createCenteredSquareImage($originalPfad, $zieldateiUser, 300);

				if ($erfolg) {
					new Message("Datei hochgeladen", "Das Profilbild wurde gespeichert.");
					return;
				} else {
					$errorMessage = "Das Bild konnte nicht verarbeitet werden.";
				}
			} else {
				$errorMessage = "Fehler – die Datei wurde nicht gespeichert. Nur JPEG-Dateien sind erlaubt.";
			}
		}

		$this->start($errorMessage);
	}

	function deleteProfilePicture() {
		global $system_data;

		$userid = $this->getUserId();
		$contactid = $this->getData()->getContactIdForUser($userid);
		$zielverzeichnis = $system_data->getProfilePicturePath();
		$userPfad = $zielverzeichnis . $contactid . ".jpg";

		if (file_exists($userPfad)) {
			unlink($userPfad);
		}

		new Message("Datei gelöscht", "Das Profilbild wurde gelöscht.");
	}

	function startOptions() {
		$chPw = new Link($this->modePrefix() . "changePassword", Lang::txt("KontaktdatenView_startOptions.changePassword"));
		$chPw->addIcon("key");
		$chPw->write();

		$settings = new Link($this->modePrefix() . "settings", Lang::txt("KontaktdatenView_startOptions.settings"));
		$settings->addIcon("settings");
		$settings->write();
	}

	function changePassword() {
		$pwNote = Lang::txt("KontaktdatenView_changePassword.Message");
		$form2 = new Form(Lang::txt("KontaktdatenView_changePassword.Form"), $this->modePrefix() . "password");
		$form2->addElement("", new Field("", $pwNote, 99));
		$form2->addElement(Lang::txt("KontaktdatenView_changePassword.New"), new Field("pw1", "", FieldType::PASSWORD));
		$form2->addElement(Lang::txt("KontaktdatenView_changePassword.Repeat"), new Field("pw2", "", FieldType::PASSWORD));
		$form2->write();
	}

	function password() {
		$this->getData()->updatePassword();
		new Message(Lang::txt("KontaktdatenView_password.Message_1"), Lang::txt("KontaktdatenView_password.Message_2"));
	}

	function settings() {
		$form = new Form(Lang::txt("KontaktdatenView_settings.saveSettings"), $this->modePrefix() . "saveSettings");
		$default = $this->getData()->getSysdata()->userEmailNotificationOn() ? "1" : "0";
		$form->addElement(Lang::txt("KontaktdatenView_settings.email_notification"), new Field("email_notification", $default, FieldType::BOOLEAN));
		$form->write();
	}

	function saveSettings() {
		$this->getData()->saveSettings($this->getUserId());
		new Message(Lang::txt("KontaktdatenView_saveSettings.Message_1"), Lang::txt("KontaktdatenView_saveSettings.Message_2"));
	}

	private function createCenteredSquareImage($quellePfad, $zielPfad, $zielgroesse) {
		$originalBild = imagecreatefromjpeg($quellePfad);
		if (!$originalBild) return false;

		$srcW = imagesx($originalBild);
		$srcH = imagesy($originalBild);

		$scale = max($zielgroesse / $srcW, $zielgroesse / $srcH);
		$scaledW = intval($srcW * $scale);
		$scaledH = intval($srcH * $scale);

		$skaliertesBild = imagecreatetruecolor($scaledW, $scaledH);
		imagecopyresampled($skaliertesBild, $originalBild, 0, 0, 0, 0, $scaledW, $scaledH, $srcW, $srcH);

		$zielBild = imagecreatetruecolor($zielgroesse, $zielgroesse);
		$cropX = intval(($scaledW - $zielgroesse) / 2);
		$cropY = max(0, intval(($scaledH - $zielgroesse) * 0.25));

		imagecopy($zielBild, $skaliertesBild, 0, 0, $cropX, $cropY, $zielgroesse, $zielgroesse);

		$erfolg = imagejpeg($zielBild, $zielPfad, 90);
		chmod($zielPfad, 0644);

		imagedestroy($originalBild);
		imagedestroy($skaliertesBild);
		imagedestroy($zielBild);

		return $erfolg;
	}
}
?>