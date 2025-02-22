<?php

class StimmbildungView extends AbstractView
{
    function __construct($ctrl)
    {
        $this->setController($ctrl);
    }

    function startOptions() {
		$isAdmin = $this->getData()->readIsAdmin();
		if ($isAdmin) {
			$seriesEdit = new Link($this->modePrefix() . "finalize", "Probe abschliessen");
			$seriesEdit->addIcon("arrow-down");
			$seriesEdit->write();
			$seriesEdit = new Link($this->modePrefix() . "edit", "bearbeiten");
			$seriesEdit->addIcon("pen");
			$seriesEdit->write();
			$seriesEdit = new Link($this->modePrefix() . "groups", "Gruppenzuordnung");
			$seriesEdit->addIcon("shuffle");
			$seriesEdit->write();
		}
	}

	// format a member entry to an HTML-string
	// $entry: table entry of one member
	// $showInstrument: true if the instrument should be put into the string
    function formatOption($entry, $showInstrument) {
		// collect background color and flags/properties
        $flags = array();
        $boColor = "";
        if ($showInstrument) {
			// whether the instrument is shown
            $flags[] = $entry[2];
        }
        $participate = $entry[3]; #$entry[5];
        if ($participate == 0) {
			// whether "not available" is shown
            $boColor = " style=\"background-color: #FFa0a0\"";
            $flags[] = "nicht da";
        }

		// format to HTML-string
        if (empty($flags)) {
            return "<option$boColor value=\"" . $entry[0] . "\">" . $entry[1] . "</option>";
        }
        return "<option$boColor value=\"" . $entry[0] . "\">" . $entry[1] . " (" . implode(", ", $flags).")</option>";
    }

	// format a member entry to an HTML-string
	// $entry: table entry of one member
	// $showInstrument: true if the instrument should be put into the string
    function formatTable($entry, $showInstrument) {
		// collect background color and flags/properties
        $flags = array();
        $boColor = "";
        if ($showInstrument) {
			// whether the instrument is shown
            $flags[] = $entry[2];
        }
        $participate = $entry[3]; #$entry[5];
        if ($participate == 0) {
			// whether "not available" is shown
            $boColor = " bgcolor=\"#FFa0a0\"";
            $flags[] = "nicht da";
        }

		// format to HTML-string
        if (empty($flags)) {
            return "<tr><td$boColor>" . $entry[1] . "</td></tr>";
        }
        return "<tr><td$boColor>" . $entry[1] . " (" . implode(", ", $flags).")</td></tr>";
    }

	// main entry point of the module
    function start()
    {
		// read main module config
        $config = $this->getData()->readConfig();
		$isAdmin = $this->getData()->readIsAdmin();

		// read data
        list($participation, $slots, $sbGroups, $instruments, $members) = $this->getData()->readData($config);

		// process requested actions
		if ($isAdmin) {
			if (array_key_exists("action", $_POST) && !empty($_POST["action"])) {
				$action = $_POST["action"];
				if ($action[0] == 'p') {
					// action: add member to a slot
					$slotId = (int)substr($action, 1);
					if ($slotId >= 0 && $slotId < count($slots) && array_key_exists("slot0",$_POST)) {
						$sbgId = (int)$_POST["slot0"];
						foreach ($sbGroups as $sbg) {
							if ($sbg[2] == $slotId) {
								$this->getData()->setSbGroup($sbg[1], 0);
								$sbg[2] = 0;
							}
						}
						$this->getData()->setSbGroup($sbgId, $slotId);
						$sbGroups[$sbgId][2] = $slotId;
					}
					list($participation, $slots, $sbGroups, $instruments, $members) = $this->getData()->readData($config);
				}
				else if ($action[0] == 'm') {
					// action: remove member from a slot
					$slotId = (int)substr($action, 1);
					if ($slotId >= 0 && $slotId < count($slots)) {
						foreach ($sbGroups as $sbg) {
							if ($sbg[2] == $slotId) {
								$this->getData()->setSbGroup($sbg[1], 0);
								$sbg[2] = 0;
								break;
							}
						}
					}
					list($participation, $slots, $sbGroups, $instruments, $members) = $this->getData()->readData($config);
				}
			}
		}

		//////// PRINT HTML PAGE ////////

        echo ("<p><table><tr><td valign='top'><B>N&auml;chste Probe:</B></td><td width='24'>&#160;</td><td>");
        $rehearsal = $this->getData()->readRehearsal($config);
        if($rehearsal != null && $rehearsal != "" && count($rehearsal) >= 1) {
            $this->writeRehearsal($rehearsal, $isAdmin);
        }
        else {
            Writing::p("Keine Probe hinterlegt.");
        }
        echo ("</td></tr>\n");
        echo ("</table></p>\n");
        ?>

        <form width="100%" action="<?php echo "?mod=" . $this->getModId(); ?>" method="POST" class="row g-2 filterbox_form">
        <table width="100%" cellpadding="5"><tr>
        <td width="400" valign="top">
            <table width="400" cellpadding="5">
        <?php
		for ($i=1; $i<count($slots); $i++) {
			echo "<tr><td width='80' valign='top'><B>".$slots[$i]["name"]."</B></td><td width=10>&#160;</td>\n";
			echo "<td width='100%'>\n";
			echo "<div style=\"border: 1px solid black;padding: 6px\">\n";
			echo "<table>\n";
			for ($j=0; $j<count($sbGroups); $j++) {
				if ($sbGroups[$j][2] == $i) {
					for ($k=0; $k<count($sbGroups[$j][3]); $k++) {
						#if ($k > 0) { echo "<br/>"; }
						echo $this->formatTable($sbGroups[$j][3][$k], true) . "\n";
					}
					break;
				}
			}
			echo "</table>\n";
			echo "</div></td>\n";
			if ($isAdmin) {
				echo "<td><button type=\"submit\" name=\"action\" value=\"p".$i."\" class=\"btn btn-primary px-2 py-0\" style=\"margin-top: 0.1rem\">&lt;</button><br/>";
				echo "<button type=\"submit\" name=\"action\" value=\"m".$i."\" class=\"btn btn-primary px-2 py-0\" style=\"margin-top: 0.1rem\">&gt;</button></td>";
			}
			echo "</tr>";
		}
		echo("</table>\n");
		echo("</td>");

		if($isAdmin) {
        ?>
        <td style='padding: 10px;' width="200px" valign="top">
			<B>Gruppen:</B><BR/><BR/>
            <select name="slot0" size="24" style="width: 300px">
                <?php
                $prevInstrument = "None";
                foreach ($sbGroups as $sbg) {
					if ($sbg[1] == 0) { // ungrouped
						continue;
					}
					if ($sbg[2] == 0) { // slot 0
						$memberInd = -1;
						for ($k=0; $k<count($members); $k++) {
							if (array_key_exists("id", $members[$k]) && $members[$k]["id"] == $sbg[0]) {
								$memberInd = $k; break;
							}
						}
						if ($memberInd >= 0 && $members[$memberInd]["instrument"] != $prevInstrument) {
							if ($prevInstrument != "None") {
								echo "</optgroup>";
							}
							echo "<optgroup label=\"".$members[$memberInd]["instrumentname"]."\">";
							$prevInstrument=$members[$memberInd]["instrument"];
						}
						echo "<option value=\"$sbg[1]\">";
						for($k=0; $k<count($sbg[3]); $k++) {
							if ($k>0) {
								echo ", ";
							}
							echo $sbg[3][$k][1];
						}
						echo "</option>\n";
					}
                }
                if ($prevInstrument != "None") {
                    echo "</optgroup>";
                }
                ?>
            </select>
        </td>
        <td>&#160;</td>
<?php
		}
		else {
			echo("<td width=\"*\">&#160;</td>\n");
		}
        echo("</tr></table>\n");
        echo("</form>\n");
    }

    function finalize() {
		if (!$this->getData()->readIsAdmin()) {
			echo("keine Berechtigung");
			return;
		}

        $config = $this->getData()->readConfig();
        $rehearsals = $this->getData()->readRehearsals();
        list($participation, $slots, $sbGroups, $instruments, $members) = $this->getData()->readData($config);

        echo ("<h2>Probe/Stimmbildung nach Durchf&uuml;hrung abschliessen</h2>");
        if (array_key_exists("action", $_POST) && !empty($_POST["action"])) {
            $action = $_POST["action"];
            if ($action == 'submit') {
                $rId = (int)$_POST["rid"];
                $this->getData()->finalizeRehearsal($config, $slots, $sbGroups, $rId, $instruments, $members);
                echo "gespeichert";
            }
        }
        else {
            echo ("<p>Die geplanten Stimmbildungsgruppen werden hiermit neu geplant.</p>");
			echo ("<p>Mit der neuen Stimmbildung startet die Gruppe:</p>");
			echo ("<table><tr><td width=50>&#160;</td><td><p style=\"border: 1px solid black;padding: 6px\">");
			$nextGroup = $this->getData()->getNextGroup($sbGroups);
			if (count($nextGroup[3]) == 0) {
				print("---");
			}
			else {
				$nl = False;
				foreach ($nextGroup[3] as $m) {
					if ($nl) {
						print("<br/>\n");
					}
					else {
						$nl = True;
					}
					print("$m[1]");
				}
			}
			echo ("</p></td></tr></table>\n");

            echo "<form action=\"?mod=" . $this->getModId() . "&mode=finalize\" method=\"POST\" " .
                "class=\"row g-2 filterbox_form\">\n";
            echo "<table><tr><td>N&auml;chste aktive Probe:</td></tr>\n";
            echo "<tr><td><p><select name=\"rid\">\n";
            echo "<option value=\"-1\">(keine)</option>\n";
            for ($i = 1; $i < count($rehearsals); $i++) {
                $selected = "";
                if ($rehearsals[$i - 1]['id'] == $config["rehearsal"]) {
                    $selected = " selected";
                }
                echo "<option value=\"" . $rehearsals[$i]['id'] . "\"" . $selected . ">" . $rehearsals[$i]['begin'] . " - " .
                    $rehearsals[$i]['end'] . "</option>\n";
            }
            echo "</select></p></td></tr>\n";
            echo "<tr><td><button type=\"submit\" name=\"action\" value=\"submit\" class=\"btn btn-primary px-3 py-2\" style=\"margin-top: 0.1rem\">Probe abschliessen</button></td></tr>\n";
            echo "</table></form>\n";
        }
    }

    private function buildWhen($begin, $end) {
        $date_end = strtotime($end);
        $finish = date('H:i', $date_end);
        return Data::convertDateFromDb($begin) . " - " . $finish;
    }

    private function writeRehearsal($row, $showLink) {
        // prepare data
        $date_begin = strtotime($row["begin"]);
        $weekday = Data::convertEnglishWeekday(date("D", $date_begin));
        $when = $this->buildWhen($row["begin"], $row["end"]);

        $conductor = "";
        if(isset($row["conductor"]) && $row["conductor"] != 0) {
            $conductor .= "Dirigent: ";
            $conductor .= $this->getData()->adp()->getConductorname($row["conductor"]);
        }

        // put output together
        ?>
        <div class="card mb-2 p-2">
            <div class=""><?php
		if ($showLink) {
			$href = "?mod=5" . "&mode=view&id=" . $row["id"];
            echo "<a href=\"$href\">$weekday $when</a><br/>\n";
		} else{
			echo "$weekday $when<br/>\n";
		}
?>                <span class=""><?php echo $conductor; ?></span>
                <span class="text-muted"><?php echo Lang::txt("Proben_status." . $row["status"]); ?></span>
            </div>
            <div class="">
                <span class="fw-bold"><?php echo $row["name"]; ?></span>
            </div>
        </div>
        <?php
    }

    function edit()
    {
		if (!$this->getData()->readIsAdmin()) {
			echo("keine Berechtigung");
			return;
		}

		// read data
        $config = $this->getData()->readConfig();
		list($participation, $slots, $sbGroups, $instruments, $members) = $this->getData()->readData($config);
        $rehearsals = $this->getData()->readRehearsals();

		// perform actions
        if (array_key_exists("action", $_POST) && !empty($_POST["action"])) {
            $action = $_POST["action"];
            if ($action == 'rehearsal') {
                $rId = (int)$_POST["rid"];
                $this->getData()->setRehearsal($rId);

				$numSlots = (int)$_POST["numSlots"];
				if ($numSlots == 0) {
					$numSlots = count($slots)-1;
				}
				$newSlots = array($numSlots+1);
				$newSlots[0] = $slots[0];
				for ($i=1; $i<$numSlots+1; $i++) {
					if (array_key_exists("slot$i", $_POST)) {
						$newSlots[$i] = array("id" => $i, "name" => $_POST["slot$i"]);
					}
					else {
						$newSlots[$i] = array("id" => $i, "name" => "");
					}
				}

				$this->getData()->setSlots($slots, $newSlots, $sbGroups);

                echo "&Auml;nderungen gespeichert";
                return;
            }
        }

		// show HTML
        echo "<form action=\"?mod=" . $this->getModId() . "&mode=edit\" method=\"POST\" ".
            "class=\"row g-2 filterbox_form\">\n";
        echo "<div><b>Nächste Probe:</b><br/>\n";
        echo "<select name=\"rid\">\n";
        for ($i=1; $i<count($rehearsals); $i++) {
            $selected = "";
            if ($rehearsals[$i]['id'] == $config["rehearsal"]) {
                $selected = " selected";
            }
            echo "<option value=\"".$rehearsals[$i]['id']."\"".$selected.">".$rehearsals[$i]['begin']." - ".
                $rehearsals[$i]['end']."</option>\n";
        }
        echo "</select></div>\n";

		echo "&#160;<br/>\n";
		echo "<div><b>Anzahl Slots: ".(count($slots)-1)."</b><br/>(Achtung: &Auml;nderung entfernt die derzeitige Stimmbildungs-Planung, danach bitte neu zusammenstellen)<br/>\n";
		echo "<select name=\"numSlots\"><option value=\"0\">(keine &Auml;nderung)</option>\n";
		for ($i=1; $i<12; $i++) {
			echo "<option value=\"$i\">$i</option>\n";
		}
		echo "</select></div>\n";

		echo "&#160;<br/>\n";
		echo "<div><b>Slotbenennung:</b><br/>";
		echo "<table cellpadding=2>";
		for ($i=1; $i<count($slots); $i++) {
			$value = "";
			if ($i < count($slots)) {
				$value = $slots[$i]["name"];
			}
			echo "<tr><td width=100 align=\"right\"><B>Slot $i:</B></td><td><input type=\"text\" name=\"slot$i\" value=\"$value\"></td></tr>";
		}
		echo "</table></div>";
		echo "&#160;<br/>\n";
        echo "<div><button type=\"submit\" name=\"action\" value=\"rehearsal\" class=\"btn btn-primary px-3 py-2\" style=\"margin-top: 0.1rem\">Speichern</button><br/>\n";
		echo "</div>\n";
        echo "</form>\n";
    }

	function groups() {
		if (!$this->getData()->readIsAdmin()) {
			echo("keine Berechtigung");
			return;
		}

		// read data
        $config = $this->getData()->readConfig();
		list($participation, $slots, $sbGroups, $instruments, $members) = $this->getData()->readData($config);

		// perform actions
        if (array_key_exists("action", $_POST) && !empty($_POST["action"])) {
            $action = $_POST["action"];
			if ($action[0] == 'p') {
				// action: add member to an sbGroup
				$sbGroupId = (int)substr($action, 1);
				if ($sbGroupId > 0 && array_key_exists("sbg0", $_POST)) {
					$memberId = (int)$_POST["sbg0"];

					$zeroGroupId = -1;
					foreach ($sbGroups as $sbg => $sbGroup) {
						if ($sbGroup[1] == 0) { $zeroGroupId = $sbg; break; }
					}

					if (array_key_exists($zeroGroupId, $sbGroups)) {
						$memberLocInd = -1;
						for ($i = 0; $i < count($sbGroups[$zeroGroupId][3]); $i++) {
							if ($sbGroups[$zeroGroupId][3][$i][0] == $memberId) {
								$memberLocInd = $i;
								break;
							}
						}
						if ($memberLocInd >= 0) {
							$this->getData()->setMemberSbgroup($memberId, $sbGroupId);
							list($participation, $slots, $sbGroups, $instruments, $members) = $this->getData()->readData($config);
						}
					}
				}
			}
			else if ($action[0] == 'm') {
				// action: remove member from an sbGroup
				$sbGroupId = (int)substr($action, 1);
				if ($sbGroupId > 0 && array_key_exists("sbg$sbGroupId",$_POST)) {
					$memberId = (int)$_POST["sbg$sbGroupId"];
					$oldGroupId = -1;
					foreach ($sbGroups as $sbg => $sbGroup) {
						if ($sbGroup[1] == $sbGroupId) { $oldGroupId = $sbg; break; }
					}

					if (array_key_exists($oldGroupId, $sbGroups)) {
				$memberLocInd = -1;
						for ($i = 0; $i < count($sbGroups[$oldGroupId][3]); $i++) {
							if ($sbGroups[$oldGroupId][3][$i][0] == $memberId) {
								$memberLocInd = $i;
								break;
							}
						}
						if ($memberLocInd >= 0) {
							$this->getData()->setMemberSbgroup($memberId, 0);
							list($participation, $slots, $sbGroups, $instruments, $members) = $this->getData()->readData($config);
						}
					}
				}
			}
        }

        echo "<form action=\"?mod=" . $this->getModId() . "&mode=groups\" method=\"POST\" ".
            "class=\"row g-2 filterbox_form\">\n";
		echo "<div><b>Gruppenzuordnung:</b><br/>";
        echo '<table width=100% cellpadding=5><tr>';
        echo "<td width=\"300\" valign=\"top\">";
		echo "<table width=\"100%\" cellpadding=\"5\">";
		$zeroGroupId = -1;
		$newSbgId = 1;
		foreach ($sbGroups as $sbg => $sbGroup) {
			if ($sbGroup[1] == 0) { $zeroGroupId = $sbg; continue; }
			if ($sbGroup[1] + 1 > $newSbgId) { $newSbgId = $sbGroup[1] + 1; }
			echo "<tr>";
			echo "<td width='240'><select style=\"overflow-y: auto; width: 240px;\" name=\"sbg".$sbGroup[1]."\" size=\"3\">";
			for ($j=0; $j<count($sbGroup[3]); $j++) {
				echo $this->formatOption($sbGroup[3][$j], true);
			}
			echo "</select></td>";
			echo "<td><button type=\"submit\" name=\"action\" value=\"p".$sbGroup[1]."\" class=\"btn btn-primary px-2 py-0\" style=\"margin-top: 0.1rem\">&lt;</button><br/>";
			echo "<button type=\"submit\" name=\"action\" value=\"m".$sbGroup[1]."\" class=\"btn btn-primary px-2 py-0\" style=\"margin-top: 0.1rem\">&gt;</button></td>";
			echo "</tr>";
		}
		echo "<tr>";
		echo "<td width='240'><select style=\"overflow-y: auto; width: 240px;\" name=\"sbg".$newSbgId."\" size=\"3\">&#160;</select></td>";
		echo "<td><button type=\"submit\" name=\"action\" value=\"p".$newSbgId."\" class=\"btn btn-primary px-2 py-0\" style=\"margin-top: 0.1rem\">&lt;</button><br/>";
		echo "<button type=\"submit\" name=\"action\" value=\"m".$newSbgId."\" class=\"btn btn-primary px-2 py-0\" style=\"margin-top: 0.1rem\">&gt;</button></td>";
		echo "</tr>";
		echo "            </table>";
		echo "        </td>";
		echo "        <td style='padding: 10px;' width=\"200px\" valign=\"top\">";
		echo "            <select name=\"sbg0\" size=\"24\" style=\"width: 300px\">";
		if ($zeroGroupId >= 0 and array_key_exists($zeroGroupId, $sbGroups)) {
			$prevInstrument = "None";
			for ($j=0; $j<count($sbGroups[$zeroGroupId][3]); $j++) {
				if ($sbGroups[$zeroGroupId][3][$j][2] != $prevInstrument) {
					if ($prevInstrument != "None") {
						echo "</optgroup>";
					}
					echo "<optgroup label=\"".$sbGroups[$zeroGroupId][3][$j][2]."\">";
					$prevInstrument=$sbGroups[$zeroGroupId][3][$j][2];
				}
				echo $this->formatOption($sbGroups[$zeroGroupId][3][$j], false);
			}
			if ($prevInstrument != "None") {
				echo "</optgroup>";
			}
		}
		echo "            </select>";
		echo "        </td>";

		echo "        <td width=*>&#160;</td>";
		echo "        </tr></table>";
		echo "        </form>";
	}
}
?>
