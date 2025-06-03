<?php
/** hL: get all events >= 2 years ago */
class CalendarData extends AbstractLocationData {

	private $startdata;
	private $mitspielerdata;
	
	/** @var AppointmentData */
	private $appointmentdata;
	
	public static $colExchange = array(
		"contact" => array("name", "surname"),
		"location" => array("name")
	);
	
	function __construct($dir_prefix = "") {
		$this->fields = array(
			"id" => array(Lang::txt("CalendarData_construct.id"), FieldType::INTEGER),
			"begin" => array(Lang::txt("CalendarData_construct.begin"), FieldType::DATETIME),
			"end" => array(Lang::txt("CalendarData_construct.end"), FieldType::DATETIME),
			"name" => array(Lang::txt("CalendarData_construct.name"), FieldType::CHAR),
			"location" => array(Lang::txt("CalendarData_construct.location"), FieldType::REFERENCE),
			"contact" => array(Lang::txt("CalendarData_construct.contact"), FieldType::REFERENCE),
			"notes" => array(Lang::txt("CalendarData_construct.notes"), FieldType::TEXT)
		);
		
		$this->references = array(
			"location" => "location",
			"contact" => "contact"
		);
		
		$this->table = "reservation";
		require_once($dir_prefix . $GLOBALS["DIR_DATA_MODULES"] . "startdata.php");
		require_once($dir_prefix . $GLOBALS["DIR_DATA_MODULES"] . "mitspielerdata.php");
		
		$this->startdata = new StartData($dir_prefix);
		$this->mitspielerdata = new MitspielerData($dir_prefix);
		$this->init($dir_prefix);
	}
	
	public function getJoinedAttributes() {
		return $this->colExchange;
	}
	
	public function setAppointmentData($appointmentData) {
		$this->appointmentdata = $appointmentData;
	}
	
	private function reduce_data($entityType, $dbsel, $fields, $key_replace=array(), $title_prefix="", $link="#") {
		$result = array();

		$modName = null;
		switch($entityType) {
			case "rehearsal": $modName = "Proben"; break;
			case "phase": $modName = "Probenphasen"; break;
			case "concert": $modName = "Konzerte"; break;
			case "vote": $modName = "Abstimmung"; break;
			case "contact": $modName = "Kontakte"; break;
			case "reservation": $modName = "Calendar"; break;
			case "appointment": $modName = "Calendar"; break;
		}
		$modAccess = false;
		if($modName != null) {
			$modAccess = $this->getSysdata()->userHasPermission($this->getSysdata()->getModuleId($modName));
		}
		
		for($i = 1; $i < count($dbsel); $i++) {
			$row = $dbsel[$i];
			$res_row = array();
			$res_row["details"] = array();
			
			foreach($fields as $field) {
				$val = $row[$field] ?? null;
				if(isset($key_replace[$field])) {
					$replaceKey = $key_replace[$field];
					$res_row[$replaceKey] = $val;
				} else {
					$res_row[$field] = $val;
				}

				if(($field === "begin" || $field === "end") && $val) {
					$res_row[$field] = str_replace(" ", "T", $val);
				}

				if($field === "id") continue;

				$detailValue = $val;
				if(in_array($field, ["begin", "end", "approve_until", "birthday"])) {
					$detailValue = Data::convertDateFromDb($val);
				}
				$detailValue = $detailValue ?? "";

				if(isset($key_replace[$field])) {
					$res_row["details"][Lang::txt($replaceKey)] = $detailValue;
				} else {
					$res_row["details"][Lang::txt("calendar_" . $field)] = $detailValue;
				}
			}

			if(isset($res_row["title"])) {
				$res_row["title"] = $title_prefix . " " . $res_row["title"];
			} else {
				$res_row["title"] = $title_prefix;
			}

			$res_row["bnoteType"] = $entityType;
			$res_row["link"] = $link . $res_row["id"];
			$res_row["access"] = $modAccess;
			$res_row["groupId"] = $entityType;
			$result[] = $res_row;
		}

		return $result;
	}

	function getEvents() {
		$startLimit = date("Y-m-d", strtotime("-2 years"));

		// alle Rehearsals >= -2 Jahre
		$rehs_db = $this->database->getSelection(
			"SELECT id, begin, end, approve_until, notes FROM rehearsal WHERE begin >= ? ORDER BY begin ASC",
			array(array("s", $startLimit))
		);

		// alle Phasen (keine Zeitfilter)
		$phases_db = $this->adp()->getUsersPhases();

		// alle Concerts >= -2 Jahre
		$concerts_db = $this->database->getSelection(
			"SELECT * FROM concert WHERE begin >= ? ORDER BY begin ASC",
			array(array("s", $startLimit))
		);

		// alle Appointments >= -2 Jahre
		$appointments_db = $this->database->getSelection(
			"SELECT a.*, l.name as locationname FROM appointment a JOIN location l ON a.location = l.id WHERE a.begin >= ? ORDER BY a.begin ASC",
			array(array("s", $startLimit))
		);

		// Stimmen, Kontakte, Reservierungen wie bisher
		$votes_db = $this->startdata->getVotesForUser();
		$contacts_db = $this->mitspielerdata->getMembers();
		$reservations_db = $this->findAllNoRef();

		// Geburtstage bearbeiten
		$contacts_db_edit = array();
		foreach($contacts_db as $i => $row) {
			if($i == 0) {
				$contacts_db_edit[] = $row;
				continue;
			}
			if(empty($row["birthday"]) || $row["birthday"] === "-") continue;
			$row["birthday"] = date("Y") . substr($row["birthday"], 4);
			$row["title"] = $row["name"] . " " . $row["surname"];
			$contacts_db_edit[] = $row;
		}

		$rehs = $this->reduce_data("rehearsal", $rehs_db,
			array("id", "begin", "end", "approve_until", "notes"),
			array("begin" => "start"),
			Lang::txt("CalendarData_getEvents.rehearsal"),
			"?mod=" . $this->getSysdata()->getModuleId("Proben") . "&mode=view&id="
		);
		$phases = $this->reduce_data("phase", $phases_db,
			array("id", "name", "begin", "end", "notes"),
			array("begin" => "start", "name" => "title"),
			"?mod=" . $this->getSysdata()->getModuleId("Probenphasen") . "&mode=view&id="
		);
		$concerts = $this->reduce_data("concert", $concerts_db,
			array("id", "title", "begin", "end", "approve_until", "location_name", "outfit", "notes"),
			array("begin" => "start"),
			Lang::txt("CalendarData_getEvents.concert"),
			"?mod=" . $this->getSysdata()->getModuleId("Konzerte") . "&mode=view&id="
		);
		$votes = $this->reduce_data("vote", $votes_db,
			array("id", "name", "end"),
			array("end" => "start", "name" => "title"),
			Lang::txt("CalendarData_getEvents.end_vote"),
			"?mod=" . $this->getSysdata()->getModuleId("Abstimmung") . "&mode=view&id="
		);
		$contacts = $this->reduce_data("contact", $contacts_db_edit,
			array("id", "birthday", "title"),
			array("birthday" => "start"),
			Lang::txt("CalendarData_getEvents.birthday"),
			"?mod=" . $this->getSysdata()->getModuleId("Kontakte") . "&mode=view&id="
		);
		$reservations = $this->reduce_data("reservation", $reservations_db,
			array("id", "begin", "end", "name"),
			array("begin" => "start", "name" => "title"),
			Lang::txt("CalendarData_getEvents.reservation"),
			"?mod=" . $this->getSysdata()->getModuleId("Calendar") . "&mode=view&id="
		);
		$appointments = $this->reduce_data("appointment", $appointments_db,
			array("id", "begin", "end", "name"),
			array("begin" => "start", "name" => "title"),
			Lang::txt("CalendarData_getEvents.appointment"),
			"?mod=" . $this->getSysdata()->getModuleId("Calendar") . "&mode=appointments&func=view&id="
		);

		return array_merge($rehs, $phases, $concerts, $votes, $contacts, $reservations, $appointments);
	}

	function getContact($id) {
		return $this->database->fetchRow("SELECT * FROM contact WHERE id = ?", array(array("i", $id)));
	}

	function getCustomData($id) {
		return $this->getCustomFieldData('v', $id);
	}

	function create($values) {
		$id = parent::create($values);
		$this->createCustomFieldData('v', $id, $values);
	}

	function update($id, $values) {
		parent::update($id, $values);
		$this->updateCustomFieldData('v', $id, $values);
	}

	function delete($id) {
		$this->deleteCustomFieldData('v', $id);
		parent::delete($id);
	}
}