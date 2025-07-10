<?php

/**
 * View for vote module
 * @author matti, hL:  showAllTable, archive, reopen, no is_date, no is_multi, result neu, delete done
 */
class AbstimmungView extends CrudView {
	
	private $entityName_option;
	private $entityName_options;
	
	/**
	 * Create the locations view.
	 */
	function __construct($ctrl) {
		$this->setController($ctrl);
		$this->setEntityName(Lang::txt("AbstimmungView_construct.EntityName"));
		$this->setAddEntityName(Lang::txt("AbstimmungView_construct.addEntityName"));
		$this->entityName_option = Lang::txt("AbstimmungView_construct.option");
		$this->entityName_options = Lang::txt("AbstimmungView_construct.options");
	}
	
	function writeTitle() {
		Writing::h2(Lang::txt("AbstimmungView_writeTitle_yourVotes"));
	}

	function startOptions() {
		parent::startOptions();
		
		$arc = new Link($this->modePrefix() . "archive", Lang::txt("AbstimmungView_startOptions.archive"));
		$arc->addIcon("archive");
		$arc->write();
	}

	function showAllTable() {
	// Nur offene Umfragen laden
	$votes = $this->getData()->getOpenVotesWithAuthorName();
	$table = new Table($votes);

	// Zeile anklickbar machen
	$table->setEdit("id");
	$table->changeMode("view&resultview=true");

	// Unnötige Spalten entfernen
	$table->removeColumn("id");
	$table->removeColumn("is_multi");
	$table->removeColumn("is_date");

	// Überschriften und Formate
	$table->renameHeader("name", "Laufende Umfragen");
	$table->renameHeader("end", "Umfrageende");
	$table->renameHeader("author_name", "Erstellt von");
	$table->renameHeader("is_finished", "Geschlossen");
	$table->setColumnFormat("end", "DATE");
	$table->setColumnFormat("is_finished", function($val) {
		return "Nein";
	});

	$table->write();

	// Abstand und Hinweis
	echo '<p>&nbsp;</p>';
	Writing::p('Eine Umfrage bearbeiten oder schliessen kann die Person, die sie erstellt hat.');
	}
	
	function addEntityTitle() {
		return Lang::txt($this->getAddEntityName());
	}

	function addEntityForm() {
	echo '<div style="margin-bottom:1.2em; font-size:1.13em;">
		Gebe den Namen der Umfrage und das Umfrageende ein. Wähle die Gruppe aus, an die sich die Umfrage richtet. Speichern mit Klick auf OK unten.
	</div>';

	$form = new Form("", $this->modePrefix() . "add");
	// Fügt automatisch alle Felder (u.a. is_date, is_multi) ein
	$form->autoAddElementsNew($this->getData()->getFields());

	// Nicht anzeigen:
	$form->removeElement("id");
	$form->removeElement("author");
	$form->removeElement("is_finished");
	$form->removeElement("is_date");   // ← Checkbox für Datumabfrage entfernen
	$form->removeElement("is_multi");  // ← Checkbox für Ja-Nein-Vielleicht entfernen

	// Gruppen-Selector bleibt erhalten
	$groups = $this->getData()->adp()->getGroups(true, true);
	$gs = new GroupSelector($groups, array(), "group");
	$gs->setNameColumn("name_member");
	$form->addElement(Lang::txt("AbstimmungView_addEntityForm.voters"), $gs);

	$form->write();
	}

	/**
	* Liest die eingeladenen Gruppen für die aktuelle Umfrage und gibt sie als kommagetrennte Liste zurück.
	* @param int $voteId
	* @return string
	*/
	protected function invitedGroupsString($voteId) {
	$groups = $this->getData()->getInvitedGroupsForVote($voteId); // siehe Data-Klasse!
	if (!$groups || count($groups) === 0) {
		return '<i>keine</i>';
	}
	// Alle Gruppennamen in ein Array, dann kommasepariert zusammenfügen
	$names = array();
	foreach ($groups as $g) {
		if (!empty($g['name'])) $names[] = htmlspecialchars($g['name'], ENT_QUOTES, 'UTF-8');
	}
	return implode(', ', $names);
	}
	
	function viewTitle() {
		if(isset($_GET["resultview"]) && $_GET["resultview"] == "true"
				|| (!$this->getData()->isUserAuthorOfVote($this->getUserId(), $_GET["id"])
						&& !$this->getData()->getSysdata()->isUserSuperUser())) {
			// result title
			$this->checkID();
			$vote = $this->getData()->findByIdNoRef($_GET["id"]);
			return $vote["name"] . " - Ergebnis";
		}
		return Lang::txt("AbstimmungView_view.header");
	}
	
	function view() {
		$this->checkID();
		if(isset($_GET["resultview"]) && $_GET["resultview"] == "true"
				|| (!$this->getData()->isUserAuthorOfVote($this->getUserId(), $_GET["id"])
				&& !$this->getData()->getSysdata()->isUserSuperUser())) {
			$this->result();
		}
		else {
			// show the details
			$this->viewDetailTable();
		}
	}

	function viewOptions() {
	// Hole die Umfrage-Daten, damit wir den Status kennen
	$vote = $this->getData()->findByIdNoRef($_GET['id']);

	if (
		(isset($_GET['resultview']) && $_GET['resultview'] === 'true')
		|| (
			!$this->getData()->isUserAuthorOfVote($this->getUserId(), $_GET['id'])
			&& !$this->getData()->getSysdata()->isUserSuperUser()
		)
	) {
		// ← Zurück-Button: Archiv oder Modul-Startseite
		if (isset($_GET['from']) && $_GET['from'] === 'history') {
			$back = new Link(
				$this->modePrefix() . 'archive',
				Lang::txt('AbstimmungView_viewOptions.back')
			);
		} else {
			$back = new Link(
				'?mod=14',
				Lang::txt('AbstimmungView_viewOptions.back')
			);
		}
		$back->addIcon('arrow-left');
		$back->write();

		// Umfragedetails (nur für den Ersteller)
		if ($this->getData()->isUserAuthorOfVote($this->getUserId(), $_GET['id'])) {
			$detailsBtn = new Link(
				$this->modePrefix() . 'view&id=' . $_GET['id'],
				'Umfragedetails'
			);
			$detailsBtn->addIcon('info-circle');
			$detailsBtn->write();
		}

		// Jetzt Votum abgeben (bei aktiver Umfrage)
		if ($this->getData()->isVoteActive($_GET['id'])) {
			$voteBtn = new Link(
				'?mod=1&mode=voteOptions&id=' . $_GET['id'],
				Lang::txt('AbstimmungView_viewOptions.now')
			);
			$voteBtn->addIcon('checkmark');
			$voteBtn->write();
		}

		// **Umfrage beenden-Button (nur anzeigen, wenn Umfrage offen ist)**
		if (
			($this->getData()->isUserAuthorOfVote($this->getUserId(), $_GET['id'])
			|| $this->getData()->getSysdata()->isUserSuperUser())
			&& ($vote['is_finished'] != 1)
		) {
			$finishBtn = new Link(
				$this->modePrefix() . 'finish&id=' . $_GET['id'],
				Lang::txt('AbstimmungView_viewOptions.finish')
			);
			$finishBtn->addIcon('x-circle-fill');
			$finishBtn->write();
		}
	}
	else {
		// Detailview (Umfragedetails)
		$this->backToStart();

		// 1) Umfrage bearbeiten
		$edit = new Link(
			$this->modePrefix() . 'edit&id=' . $_GET['id'],
			Lang::txt('AbstimmungView_viewOptions.edit')
		);
		$edit->addIcon('pen');
		$edit->write();

		// 2) Optionen
		$opt = new Link(
			$this->modePrefix() . 'options&id=' . $_GET['id'],
			$this->entityName_options
		);
		$opt->addIcon('list-check');
		$opt->write();

		// 3) Ergebnis direkt rechts neben Optionen
		$res = new Link(
			$this->modePrefix() . 'result&id=' . $_GET['id'],
			Lang::txt('AbstimmungView_additionalViewButtons.result')
		);
		$res->addIcon('file-bar-graph');
		$res->write();

		// 4) Umfrage beenden-Button (nur wenn noch offen)
		if ($vote['is_finished'] != 1) {
			$del = new Link(
				$this->modePrefix() . 'finish&id=' . $_GET['id'],
				Lang::txt('AbstimmungView_viewOptions.finish')
			);
			$del->addIcon('x-circle-fill');
			$del->write();
		}
	}
	}
	
	function add() {
		// validate
		$this->getData()->validate($_POST, true);
		
		// process
		$vid = $this->getData()->create($_POST);
		
		// write success
		new Message(Lang::txt("AbstimmungView_add.saved_entity", array($this->getEntityName())),
				Lang::txt("AbstimmungView_add.saved_message"));
		
		// show options link
		$lnk = new Link($this->modePrefix() . "options&id=$vid", Lang::txt("AbstimmungView_add.add_options"));
		$lnk->addIcon("plus");
		$lnk->write();
	}
	
	function options() {
	$this->checkID();
	$vote = $this->getData()->findByIdNoRef($_GET["id"]);

	// add a new element if posted – nur wenn das Feld nicht leer ist!
	if (isset($_POST["name"]) && trim($_POST["name"]) !== "") {
		$this->getData()->addOption($_GET["id"]);
	}
	// Date-Range-Optionen entfallen komplett, da is_date immer 0

	// show options that are already present
	Writing::h2($vote["name"] . " - " . Lang::txt("AbstimmungView_options.options"));
	$options = $this->getData()->getOptions($_GET["id"]);

	Writing::p(Lang::txt("AbstimmungView_options.remove_option_tip"));

	echo "<ul>";
	for($i = 1; $i < count($options); $i++) {
		$href = $this->modePrefix() . "delOption&oid=" . $options[$i]["id"] . "&id=" . $_GET["id"];
		// Immer nur Name anzeigen
		$val = $options[$i]["name"];
		echo " <li><a href=\"$href\">$val</a></li>";
	}
	echo "</ul>";
	if(count($options) < 2) {
		Writing::p("<i>" . Lang::txt("AbstimmungView_options.no_options_yet") . "</i>");
	}

	// show add options form
	$form = new Form(Lang::txt("AbstimmungView_options.add_entity", array($this->entityName_option)), $this->modePrefix() . "options&id=" . $_GET["id"]);
	$form->addElement(Lang::txt("AbstimmungView_options.name"), new Field("name", "", FieldType::CHAR));
	$form->write();

	// --- JavaScript: Kein Submit bei leerem Feld ---
	echo <<<EOT
	<script>
	document.addEventListener('DOMContentLoaded', function() {
	var form = document.querySelector('form[action*="options&id="]');
	if (!form) return;
	var nameField = form.querySelector('input[name="name"]');
	var okButton  = form.querySelector('button[type="submit"],input[type="submit"]');
	if (okButton && nameField) {
		okButton.addEventListener('click', function(e) {
			if (nameField.value.trim() === "") {
				e.preventDefault();
				nameField.focus();
			}
		});
	}
	});
	</script>
	EOT;
	}	

	function optionsOptions() {
		$this->backToViewButton($_GET["id"]);
	}
	
	function delOption() {
	$this->checkID();
	$this->getData()->deleteOption($_GET["oid"]);
	$this->options();
	}

	function viewDetailTable() {
	// 1) ID prüfen und Datensatz laden
	$this->checkID();
	$vote = $this->getData()->getVoteWithAuthorName($_GET['id']);
	$id   = $vote['id'];

	// 2) Dataview initialisieren
	$dv = new Dataview();

	// Umfragetitel
	$dv->addElement(
		Lang::txt("AbstimmungView_viewDetailTable.title"),
		htmlspecialchars($vote['name'] ?? '', ENT_QUOTES, 'UTF-8')
	);

	// Optionen unter dem Titel, wie vorher
	$options = $this->getData()->getOptions($id);
	$labels  = [];
	foreach ($options as $idx => $opt) {
		if ($idx === 0) continue;
		$name = $opt['name'] ?? '';
		if ($name !== '') {
			$labels[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
		}
	}
	$dv->addElement(
		$this->entityName_options,
		$labels
			? implode(' | ', $labels)
			: '<i>Keine Optionen hinzugefügt</i>'
	);

	// Ende-Datum
	$dv->addElement(
		Lang::txt("AbstimmungView_viewDetailTable.end"),
		Data::convertDateFromDb($vote['end'])
	);

	// Eingeladene Gruppen (NEU)
	$dv->addElement(
		'Eingeladen',
		$this->invitedGroupsString($id)
	);

	// Ersteller
	$dv->addElement(
		'Erstellt von',
		htmlspecialchars($vote['author_name'] ?? '', ENT_QUOTES, 'UTF-8')
	);

	// Umfrage-Status + Buttons
	$finished	  = ($vote['is_finished'] == 1);
	$status		= $finished ? 'Ja' : 'Nein';
	$buttonReopen  = '';
	$buttonDelete  = '';

	if ($finished) {
		$buttonReopen = '<a href="'
			. $this->modePrefix() . 'reopenVote&id=' . $id
			. '" style="margin-left:18px;border:2px solid #888;'
			. 'background:#f5f5f5;color:green;font-size:0.85em;'
			. 'font-weight:bold;padding:3px 12px;border-radius:4px;'
			. 'text-decoration:none;display:inline-block;">'
			. 'Umfrage wieder öffnen</a>';

		$deleteUrl = $this->modePrefix() . 'deleteVote&id=' . $id;
		$buttonDelete = '<a href="javascript:void(0)"'
			. ' onclick="if(confirm(\'Die Umfrage wird endgültig gelöscht. Jetzt Löschen?\'))'
			. '{window.location=\'' . $deleteUrl . '\';}"'
			. ' style="margin-left:8px;border:2px solid #888;'
			. 'background:#f5f5f5;color:#c00;font-size:0.85em;'
			. 'font-weight:bold;padding:3px 12px;border-radius:4px;'
			. 'text-decoration:none;display:inline-block;">'
			. 'Umfrage löschen</a>';
	}

	$dv->addElement(
		'Geschlossen',
		'<div style="display:flex;align-items:center;">'
		. $status
		. $buttonReopen
		. $buttonDelete
		. '</div>'
	);

	// 3) Dataview ausgeben
	$dv->write();
	}
	
	

	protected function editEntityForm() {
	$vote = $this->getData()->findByIdNoRef($_GET["id"]);
	// Nur das Bearbeitungsformular, kein Button mehr
	$form = new Form("", $this->modePrefix() . "edit_process&id=" . $_GET["id"]);
	$form->addElement("Umfragename", new Field("name", $vote["name"], FieldType::CHAR));
	$form->addElement("Umfrageende", new Field("end", $vote["end"], FieldType::DATETIME));
	
	// Hinweistext geschlossene Umfrage
	if ($vote["is_finished"] == 1) {
		echo '<p>Die Umfrage ist geschlossen. Mit Klick auf OK wird sie wieder geöffnet. Klick auf Zurück lässt sie unverändert.</p>';
	}

	$form->write();
	}
	
	function group() {
		$this->checkID();
		$vote = $this->getData()->findByIdNoRef($_GET["id"]);
		
		// add a set of users when requested
		if(isset($_GET["func"]) && $_GET["func"] == "addAllMembers") {
			$this->getData()->addAllMembersAndAdminsToGroup($_GET["id"]);
		}
		
		// add a new element if posted
		if(isset($_POST["user"])) {
			$this->getData()->addToGroup($_GET["id"], $_POST["user"]);
		}
		
		// show options that are already present
		Writing::h2($vote["name"] . " - " . Lang::txt("AbstimmungView_group.voters"));
		$group = $this->getData()->getGroup($_GET["id"]);
		
		Writing::p(Lang::txt("AbstimmungView_group.clickToRemoveUser"));
		
		echo "<ul>";
		for($i = 1; $i < count($group); $i++) {
			$href = $this->modePrefix() . "delFromGroup&uid=" . $group[$i]["id"] . "&id=" . $_GET["id"];
			$val = $group[$i]["name"] . " " . $group[$i]["surname"];
			echo " <li><a href=\"$href\">$val</a></li>";
		}
		echo "</ul>";
		if(count($group) < 2) {
			Writing::p("<i>" . Lang::txt("AbstimmungView_group.noVotersYet") . "</i>");
		}
			
		// show add users form
		$form = new Form(Lang::txt("AbstimmungView_group.addVoter"), $this->modePrefix() . "group&id=" . $_GET["id"]);
		$users = $this->getData()->getUsers();
		$dd = new Dropdown("user");
		$amIinUsers = false;
		for($i = 1; $i < count($users); $i++) {
			$dd->addOption($users[$i]["name"] . " " . $users[$i]["surname"], $users[$i]["id"]);
			if($users[$i]["id"] == $this->getUserId()) {
				$amIinUsers = true;
			}
		}
		if(!$amIinUsers) {
			$contact = $this->getData()->getSysdata()->getUsersContact();
			$dd->addOption($contact["name"] . " " . $contact["surname"], $this->getUserId());
		}
		$form->addElement(Lang::txt("AbstimmungView_group.voter"), $dd);
		$form->write();
		$this->verticalSpace();
	}
	
	
	function groupOptions() {
		$this->backToViewButton($_GET["id"]);
	}
	
	function delFromGroup() {
		$this->checkID();
		$this->getData()->deleteFromGroup($_GET["id"], $_GET["uid"]);
		$this->group();
	}

	function result() {
	$this->checkID();
	$this->result_overview();
	echo '<hr>';
	$this->result_table();

	// Ermitteln, ob die Umfrage abgelaufen ist
	$vote = $this->getData()->getVoteWithAuthorName($_GET["id"]);
	$voteEndRaw = isset($vote['end']) ? $vote['end'] : '';
	$now = date('Y-m-d H:i:s');
	$isFinished = false;
	if ($voteEndRaw !== '') {
		$isFinished = (strtotime($voteEndRaw) <= strtotime($now));
	}

	$summaryText = $isFinished ? 'Kein Votum - ggf. Nachricht:' : 'Noch kein Votum - ggf Nachricht:';
	echo '<br><br>';
	echo '<details style="margin-bottom:1em;">';
	echo '<summary style="font-size:0.97em; font-weight:bold; color:#444; cursor:pointer;">' . $summaryText . '</summary>';
	$this->result_table_no_vote();
	echo '</details>';
	}
		
	/**
	* Zeigt die Übersicht der Ergebnisse zur aktuellen Umfrage:
	* Name, Eingeladen, Teilgenommen, Eingeladene Gruppen und pro Option die Stimmenzahl
	*/
	function result_overview() {
	$this->checkID();
	$vid = $_GET["id"];
	$data = $this->getData();

	$voteName		  = $data->getVoteName($vid);
	$vote			  = $data->getVoteWithAuthorName($vid); // enthält auch das Enddatum
	$voteEndRaw	   = isset($vote['end']) ? $vote['end'] : '';
	$voteEnd		  = $voteEndRaw ? Data::convertDateFromDb($voteEndRaw) : '';
	$invitedCount	  = $data->getInvitedUserCount($vid);
	$participantCount = $data->getParticipantsCount($vid);
	$optionResults	 = $data->getOptionResults($vid);
	$groups		   = $data->getInvitedGroupsForVote($vid);

	// Zeitvergleich: Endedatum vs jetzt
	$now		 = date('Y-m-d H:i:s');
	$isFinished  = false;
	if ($voteEndRaw !== '') {
		$isFinished = (strtotime($voteEndRaw) <= strtotime($now));
	}

	// Optionen nach Stimmenzahl sortieren (desc)
	usort($optionResults, function($a, $b) {
		$aVotes = isset($a['votes']) ? (int)$a['votes'] : 0;
		$bVotes = isset($b['votes']) ? (int)$b['votes'] : 0;
		return $bVotes <=> $aVotes;
	});

	// Styles für Label + Wert-Spalten
	$labelStyle = 'display:inline-block;width:80px;color:#aaa;font-weight:600;';
	$valueStyle = 'display:inline-block;';

	echo '<div class="vote-overview">';

	// Umfrage-Name
	echo '<div><span style="'.$labelStyle.'">Umfrage:</span><span style="'.$valueStyle.'">' .
		htmlspecialchars((string)($voteName ?? ''), ENT_QUOTES, 'UTF-8') . '</span></div>';

	// Eingeladene Gruppen
	echo '<div><span style="'.$labelStyle.'">An:</span><span style="'.$valueStyle.'">';
	if (!empty($groups)) {
		$names = [];
		foreach ($groups as $grp) {
			if (isset($grp['name']) && $grp['name'] !== null) {
				$names[] = htmlspecialchars($grp['name'], ENT_QUOTES, 'UTF-8');
			}
		}
		echo $names ? implode(', ', $names) : '';
	}
	echo '</span></div>';

	// Umfrageende
	echo '<div><span style="'.$labelStyle.'">Bis:</span><span style="'.$valueStyle.'">' .
		htmlspecialchars((string)($voteEnd ?? ''), ENT_QUOTES, 'UTF-8') . '</span></div>';

	// Ergebnis-Überschrift
	echo '<br><span style="font-size:1.15em;font-weight:bold;">' .
		($isFinished ? 'Ergebnis' : 'Zwischenergebnis') .
		'</span><br>';

	// Teilnahmeberechtigte (bleibt schwarz wie gewünscht)
	echo 'Teilnahmeberechtigte: <strong>' . (int)$invitedCount . '</strong><br>';

	// Bisher Teilgenommen / Teilgenommen (bleibt schwarz wie gewünscht)
	if ($isFinished) {
		echo 'Teilgenommen: <strong>' . (int)$participantCount . '</strong><br>';
	} else {
		echo 'Bisher Teilgenommen: <strong>' . (int)$participantCount . '</strong><br>';
	}

	echo '<br>';

	// Optionen grafisch als Balken
	$this->result_overview_graphic($optionResults, $isFinished);

	echo '</div>';
	}

	/**
	* @param array $optionResults Array der Optionen mit Stimmenzahl (wie aus getOptionResults)
	* @param bool $isFinished	 Flag, ob Umfrage abgeschlossen ist (optional für spätere Erweiterung)
	*/
	function result_overview_graphic($optionResults, $isFinished = false) {
	if (empty($optionResults)) {
		echo '<i>Keine Optionen vorhanden</i>';
		return;
	}

	// 1. Optionen nach id sortieren
	usort($optionResults, function($a, $b) {
		return $a["id"] <=> $b["id"];
	});

	// 2. Buchstabenpräfixe über getOptionLabelMap holen
	$vid = $_GET["id"];
	$prefixMap = $this->getOptionLabelMap($vid);

	// 3. Maximale Stimmenzahl ermitteln
	$maxVotes = 0;
	foreach ($optionResults as $opt) {
		$v = isset($opt['votes']) ? (int)$opt['votes'] : 0;
		if ($v > $maxVotes) $maxVotes = $v;
	}
	if ($maxVotes === 0) $maxVotes = 1;

	// 4. Style-Block
	echo '<style>
	.vote-bar-list {
		margin: 0; padding: 0; list-style: none; max-width: 415px;
	}
	.vote-bar-item {
		display: flex; flex-direction: column; align-items: flex-start; margin-bottom: 9px; min-height: 1.2em;
	}
	.vote-bar-row {
		display: flex; align-items: flex-start; width: 100%;
	}
	.vote-bar-label {
		width: 22ch; flex: 0 0 22ch; font-weight: normal; margin-right: 8px;
		word-break: break-word; white-space: normal; text-align: left; font-size: 1em; line-height: 1.08;
		padding-top: 0px; padding-bottom: 0px;
	}
	.vote-bar-barwrap {
		flex: 1 1 auto; margin-right: 0;
		background: #D2F8D2; border-radius: 0; overflow: hidden; min-width: 30px;
		display: flex; align-items: center; height: 1em;
		max-width: 330px;
	}
	.vote-bar-bar {
		height: 100%; background: #35bb70; border-radius: 0; transition: width .3s;
		display: block;
	}
	.vote-bar-votes {
		font-weight: bold; min-width: 22px; text-align: left; 
		font-family: inherit;
		font-size: 1.03em;
		color: #222; flex: 0 0 auto; margin: 0 0 0 14px; line-height: 1.15;
		padding-top: 0px;
	}
	.vote-bar-label-rest {
		font-size: 1em; font-weight: normal; margin: 0; padding: 0 0 0 0;
		line-height: 1.13; color: #222; text-align: left;
	}
	@media (max-width: 600px) {
		.vote-bar-list { max-width: 330px; }
		.vote-bar-label { width: 13ch; flex-basis: 13ch; font-size: 0.98em; }
		.vote-bar-item { min-height: 1.05em; }
		.vote-bar-barwrap { height: 0.96em; max-width: 220px; }
		.vote-bar-votes { min-width: 18px; font-size: 0.97em; margin-left: 10px;}
	}
	</style>';

	// 5. Ausgabe der Optionen mit Prefix-Labels
	echo '<ul class="vote-bar-list">';
	foreach ($optionResults as $opt) {
		$id	= $opt['id'];
		$name  = $prefixMap[$id] ?? '<i>Keine Option</i>';
		$votes = isset($opt['votes']) ? (int)$opt['votes'] : 0;
		$widthPercent = round(($votes / $maxVotes) * 100);

		$labelRaw = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

		// Zeilenumbruch nach 20 Zeichen
		if ($name !== '<i>Keine Option</i>' && mb_strlen($labelRaw) > 20) {
			$cut = mb_strrpos(mb_substr($labelRaw, 0, 20), ' ');
			if ($cut === false || $cut < 5) $cut = 20;
			$firstLine = mb_substr($labelRaw, 0, $cut);
			$restLine  = ltrim(mb_substr($labelRaw, $cut));
		} else {
			$firstLine = $labelRaw;
			$restLine  = '';
		}

		echo '<li class="vote-bar-item">';
		echo '<div class="vote-bar-row">';
		echo '<span class="vote-bar-label">' . $firstLine . '</span>';
		echo '<span class="vote-bar-barwrap">';
		echo '<span class="vote-bar-bar" style="width:' . $widthPercent . '%"></span>';
		echo '</span>';
		echo '<span class="vote-bar-votes">' . $votes . '</span>';
		echo '</div>';
		if ($restLine !== '') {
			echo '<span class="vote-bar-label-rest">' . $restLine . '</span>';
		}
		echo '</li>';
	}
	echo '</ul>';
	}


	function result_table() {
	$data = $this->getData()->getRohErgebnisliste($_GET["id"]);

	$options = $this->getData()->getOptionResults($_GET["id"]);
	usort($options, function($a, $b) {
		return $a["id"] <=> $b["id"];
	});

	$optionMap = $this->getOptionLabelMap($_GET["id"]);

	// ALLE Werte in VOTUM dekodieren (auch 0/"Kein Votum" wird jetzt als Label behandelt, falls gemappt)
	foreach ($data as &$row) {
		if (isset($row["VOTUM"]) && is_numeric($row["VOTUM"]) && isset($optionMap[(int)$row["VOTUM"]])) {
			$row["VOTUM"] = $optionMap[(int)$row["VOTUM"]];
		}
	}

	// VOTUM auf max. 3 Wörter kürzen
	foreach ($data as &$row) {
		if (
			isset($row["VOTUM"]) &&
			is_string($row["VOTUM"])
		) {
			$words = preg_split('/\s+/', $row["VOTUM"]);
			$row["VOTUM"] = implode(" ", array_slice($words, 0, 3));
		}
	}

	$table = new Table($data);
	$table->removeColumn("vote");
	$table->showFilter(true);
	$table->write();
	}

	function result_table_no_vote() {
	$data = $this->getData()->getRohErgebnislisteNoVote($_GET["id"]);

	if (empty($data)) {
		echo "<div style='color:#888;margin-bottom:1em;'>Alle eingeladenen Personen haben abgestimmt.</div>";
		return;
	}

	$table = new Table($data);
	$table->removeColumn("vote");   // Falls Spalte vorhanden
	$table->showFilter(true);
	$table->write();
	}

	/**
	* sortiert option-ids und weist option-name präfix zu
	*/	
	private function getOptionLabelMap($voteId) {
	$optionResults = $this->getData()->getOptionResults($voteId);

	// 1. Optionen sortieren nach ID
	usort($optionResults, function($a, $b) {
		return $a["id"] <=> $b["id"];
	});

	// 2. Mapping ID → A: Optionentext (ohne ID)
	$map = [];
	$letter = 'A';
	foreach ($optionResults as $opt) {
		$label = $letter . ": " . $opt["name"];  // Kontroll-ID entfernt
		$map[$opt["id"]] = $label;
		$letter++;
	}
	return $map;
	}
	
	
	function archiveTitle() { return Lang::txt("AbstimmungView_archive.archive"); }

	function archive() {
	// Nur beendete Umfragen laden
	$votes = $this->getData()->getClosedVotesWithAuthorName();
	$table = new Table($votes);

	$table->setEdit("id");
	$table->changeMode("view&from=history"); // <--- HIER geändert!

	// Nicht benötigte Spalten entfernen
	$table->removeColumn("id");
	$table->removeColumn("is_multi");
	$table->removeColumn("is_date");

	// Spaltenüberschriften setzen
	$table->renameHeader("name", "Geschlossene Umfragen");
	$table->renameHeader("end", "Umfrageende");
	$table->renameHeader("author_name", "Erstellt von");
	$table->renameHeader("is_finished", "Geschlossen");

	// End-Datum als Datum formatieren
	$table->setColumnFormat("end", "DATE");

	// Immer "Ja" anzeigen
	$table->setColumnFormat("is_finished", function($val) {
		return "Ja";
	});

	$table->write();

	// Eine Leerzeile Abstand
	echo '<p>&nbsp;</p>';

	// Neuer Hinweistext in Standardschrift, unter der Tabelle
	Writing::p('Klicke auf eine Umfrage, um sie wieder zu öffnen oder zu löschen, wenn Du die Umfrage angelegt hat.');
	}
	
	function finish() {
		$this->checkID();
		$this->getData()->finish($_GET["id"]);
		$this->archive();
	}
	
	public function edit_process() {
	$this->checkID();
	// Checkbox speichern (0 oder 1) überschreibt crudview.php
	$_POST["is_finished"] = isset($_POST["is_finished"]) ? 1 : 0;

	if(!isset($_GET["manualValid"]) || $_GET["manualValid"] != "true") {
		$this->getData()->validate($_POST);
	}
	$this->getData()->update($_GET[$this->idParameter], $_POST);

	new Message($this->entityName, Lang::txt("CrudView_edit_process.delete_changed"));
	}
	
	public function reopenVote() {
	$this->checkID();
	$this->getData()->reopenVote($_GET["id"]);
	// Nach dem Öffnen zurück zur Detailansicht
	$this->view();
	}
	
	/**
	 * Löscht eine beendete Umfrage (inkl. Optionen und Stimmen)
	 * und zeigt danach die Archiv-Übersicht.
	 */
	function deleteVote() {
	// ID prüfen
	$this->checkID();
	$id = (int) $_GET['id'];

	// Titel holen
	$vote  = $this->getData()->getVoteWithAuthorName($id);
	$title = htmlspecialchars($vote['name'] ?? '', ENT_QUOTES, 'UTF-8');

	// Datenbank-Löschung durchführen
	$this->getData()->deleteVote($id);

	// Erfolgsmeldung: beginnt direkt mit Name und ohne ID
	new Message(
	'Umfrage gelöscht',
	'Die Umfrage [' . $title . '] und ihre Ergebnisse sind gelöscht.'
	);

	// Zurück zur Übersicht der geschlossenen Umfragen
	$this->archive();
	}

}

?>