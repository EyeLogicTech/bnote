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
			$seriesEdit = new Link($this->modePrefix() . "shuffle", "Neu zusammenstellen");
			$seriesEdit->addIcon("shuffle");
			$seriesEdit->write();
			$seriesEdit = new Link($this->modePrefix() . "finalize", "Probe abschliessen");
			$seriesEdit->addIcon("arrow-down");
			$seriesEdit->write();
			$seriesEdit = new Link($this->modePrefix() . "edit", "bearbeiten");
			$seriesEdit->addIcon("pen");
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
        $done = $entry[3];
        if ($done) {
			// whether "done" is shown
            $boColor = " style=\"background-color: #A0A0A0\"";
            $flags[] = "war schon";
        }
        $participate = $entry[5];
        if ($participate == 0) {
			// whether "not available" is shown
            $boColor = " style=\"background-color: #FFa0a0\"";
            $flags[] = "nicht da";
        }
        $alone = $entry[4];
        if ($alone) {
			// whether "alone" is shown
            $flags[] = "alleine";
        }

		// format to HTML-string
        if (empty($flags)) {
            return "<option$boColor value=\"" . $entry[0] . "\">" . $entry[1] . "</option>";
        }
        return "<option$boColor value=\"" . $entry[0] . "\">" . $entry[1] . " (" . implode(", ", $flags).")</option>";
    }

	// main entry point of the module
    function start()
    {
		// read main module config
        $config = $this->getData()->readConfig();
		$isAdmin = $this->getData()->readIsAdmin();

		// read data
        list($participation, $slots, $slotTable, $instruments, $members) = $this->getData()->readData($config);

		// process requested actions
		if ($isAdmin) {
			if (array_key_exists("action", $_POST) && !empty($_POST["action"])) {
				$action = $_POST["action"];
				if ($action[0] == 'p') {
					// action: add member to a slot
					$slotId = (int)substr($action, 1);
					if ($slotId >= 0 && $slotId < count($slots) && array_key_exists("slot0",$_POST)) {
						$memberId = (int)$_POST["slot0"];
						if (count($slotTable[$slotId]) < 3) {
							$memberLocInd = -1;
							for ($i = 0; $i < count($slotTable[0]); $i++) {
								if ($slotTable[0][$i][0] == $memberId) {
									$memberLocInd = $i;
									break;
								}
							}
							if ($memberLocInd >= 0) {
	//                            $m = $slotTable[0][$memberLocInd];
	//                            array_splice($slotTable[0], $memberLocInd, 1);
	//                            $slotTable[$slotId][] = $m;
								$this->getData()->setMemberSlot($memberId, $slotId);
								list($participation, $slots, $slotTable, $instruments, $members) = $this->getData()->readData($config);
							}
						}
					}
				}
				else if ($action[0] == 'm') {
					// action: remove member from a slot
					$slotId = (int)substr($action, 1);
					if ($slotId >= 0 && $slotId < count($slots) && array_key_exists("slot".$slotId,$_POST)) {
						$memberId = (int)$_POST["slot".$slotId];
						$memberLocInd = -1;
						for ($i = 0; $i < count($slotTable[$slotId]); $i++) {
							if ($slotTable[$slotId][$i][0] == $memberId) {
								$memberLocInd = $i;
								break;
							}
						}
						if ($memberLocInd >= 0) {
	//                        $m = $slotTable[$slotId][$memberLocInd];
	//                        array_splice($slotTable[$slotId], $memberLocInd, 1);
	//                        $slotTable[0][] = $m;
							$this->getData()->setMemberSlot($memberId, 0);
							list($participation, $slots, $slotTable, $instruments, $members) = $this->getData()->readData($config);
						}
					}
				}
				else if ($action == 'done') {
					// action: (un-)mark member as done
					if (array_key_exists("slot0",$_POST)) {
						$memberId = (int)$_POST["slot0"];
						$memberLocInd = -1;
						for ($i = 0; $i < count($slotTable[0]); $i++) {
							if ($slotTable[0][$i][0] == $memberId) {
								$memberLocInd = $i;
								break;
							}
						}
						if ($memberLocInd >= 0) {
							$slotTable[0][$memberLocInd][3] = 1 - $slotTable[0][$memberLocInd][3];
							$this->getData()->setMemberDone($memberId, $slotTable[0][$memberLocInd][3]);
						}
					}
				}
				else if ($action == 'finalize2') {
					// action: "Probe abschliessen"
					$this->showFinalize();
					return;
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

        echo ("<tr><td><B>Aktive Gruppe</B>:</td><td width='24'>&#160;</td><td>");
		$ag = $this->getData()->getActiveGroup($config, $instruments);
        $activeGroupName = $ag["name"];
        $activeGroupShownName = $ag["sname"];
		if ($activeGroupShownName != $activeGroupName) {
			echo "$activeGroupShownName (Nachholer in $activeGroupName)";
		}
		else {
			echo "$activeGroupShownName";
		}
        echo "</td></tr></table></p>\n";
        ?>
        <form width="100%" action="<?php echo "?mod=" . $this->getModId(); ?>" method="POST" class="row g-2 filterbox_form">
        <table width="100%" cellpadding="5"><tr>
        <td width="300" valign="top">
            <table width="100%" cellpadding="5">
            <?php
            for ($i=1; $i<count($slots); $i++) {
                echo "<tr><td width='80' valign='top'><B>".$slots[$i]["name"]."</B></td><td width=10>&#160;</td>";
                echo "<td width='240'><select style=\"overflow-y: auto; width: 240px;\" name=\"slot".$i."\" size=\"3\">";
                for ($j=0; $j<count($slotTable[$i]); $j++) {
                    echo $this->formatOption($slotTable[$i][$j], true);
                }
                echo "</select></td>";
				if ($isAdmin) {
					echo "<td><button type=\"submit\" name=\"action\" value=\"p".$i."\" class=\"btn btn-primary px-2 py-0\" style=\"margin-top: 0.1rem\">&lt;</button><br/>";
					echo "<button type=\"submit\" name=\"action\" value=\"m".$i."\" class=\"btn btn-primary px-2 py-0\" style=\"margin-top: 0.1rem\">&gt;</button></td>";
				}
                echo "</tr>";
            }
            ?>
            </table>
        </td>
        <td style='padding: 10px;' width="200px" valign="top">
            <select name="slot0" size="24" style="width: 300px">
                <?php
                $prevInstrument = "None";
                for ($j=0; $j<count($slotTable[0]); $j++) {
                    if ($slotTable[0][$j][2] != $prevInstrument) {
                        if ($prevInstrument != "None") {
                            echo "</optgroup>";
                        }
                        echo "<optgroup label=\"".$slotTable[0][$j][2]."\">";
                        $prevInstrument=$slotTable[0][$j][2];
                    }
                    echo $this->formatOption($slotTable[0][$j], false);
                }
                if ($prevInstrument != "None") {
                    echo "</optgroup>";
                }
                ?>
            </select>
<?php
			if ($isAdmin) {
				echo "            <div><button type=\"submit\" name=\"action\" value=\"done\" class=\"btn btn-primary px-2 py-0\" style=\"margin-top: 0.1rem\">War schon</button></div>\n";
			}
			?>
        </td>
        <td>&#160;</td>
        </tr></table>
        </form>
        <?php
    }

    function shuffle()
    {
        echo ("<h2>Stimmbildung f&uuml;r n&auml;chste Probe neu zusammenstellen</h2>");
		if (!$this->getData()->readIsAdmin()) {
			echo("keine Berechtigung");
			return;
		}

        if (array_key_exists("action", $_POST) && !empty($_POST["action"])) {
            $config = $this->getData()->readConfig();
            list($participation, $slots, $slotTable, $instruments, $members) = $this->getData()->readData($config);

            $action = $_POST["action"];
            if ($action == 'submit') {
                $this->getData()->fillSlots($config, $slots, $slotTable, $instruments, $participation, $members);
                echo "<p>Die Stimmbildung wurde neu zusammengestellt.</p>";
                return;
            }
        }

        echo "<form action=\"?mod=" . $this->getModId() . "&mode=shuffle\" method=\"POST\" " .
            "class=\"row g-2 filterbox_form\">\n";
        echo "<p>Die aktuelle Zusammenstellung wird verworfen und eine neue erstellt. Fortfahren?</p>";
        echo "<p><button type=\"submit\" name=\"action\" value=\"submit\" class=\"btn btn-primary px-3 py-2\" style=\"margin-top: 0.1rem\">Neu zusammenstellen</button></p>\n";
        echo "</form>\n";
    }

    function finalize() {
		if (!$this->getData()->readIsAdmin()) {
			echo("keine Berechtigung");
			return;
		}

        $config = $this->getData()->readConfig();
        $rehearsals = $this->getData()->readRehearsals();
        list($participation, $slots, $slotTable, $instruments, $members) = $this->getData()->readData($config);

        echo ("<h2>Probe/Stimmbildung nach Durchf&uuml;hrung abschliessen</h2>");
        if (array_key_exists("action", $_POST) && !empty($_POST["action"])) {
            $action = $_POST["action"];
            if ($action == 'submit') {
                $rId = (int)$_POST["rid"];
                $this->getData()->finalizeRehearsal($config, $slots, $slotTable, $rId, $instruments, $members);
                echo "gespeichert";
            }
        }
        else {
            echo ("<p>Die folgenden Mitglieder haben an der Stimmbildung teilgenommen:</p>");

            echo ("<p><table>");
            for ($i=1; $i<count($slots); $i++) {
                echo ("<tr><td><B>".$slots[$i]["name"]."</B>:&nbsp;</td>");
                echo ("<td>");
                for ($j=0; $j<count($slotTable[$i]); $j++) {
                    if ($j>0) {
                        echo ", ";
                    }
                    echo $slotTable[$i][$j][1];
                }
                echo ("</td></tr>");
            }
            echo ("</table></p>");

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
		list($participation, $slots, $slotTable, $instruments, $members) = $this->getData()->readData($config);
        $rehearsals = $this->getData()->readRehearsals();
		$ag = $this->getData()->getActiveGroup($config, $instruments);
        $activeGroupName = $ag["name"];
        $activeGroupShownName = $ag["sname"];

		// perform actions
        if (array_key_exists("action", $_POST) && !empty($_POST["action"])) {
            $action = $_POST["action"];
            if ($action == 'rehearsal') {
                $rId = (int)$_POST["rid"];
                $this->getData()->setRehearsal($rId);

				$asg = (int)$_POST["asg"];
				if ($asg != 0) {
					$this->getData()->resetActiveGroup($asg, $members);
				}

				$numSlots = (int)$_POST["numSlots"];
				if ($numSlots == 0) {
					$numSlots = count($slots)-1;
				}
				$newSlots = array($numSlots+1);
				$newSlots[0] = $slots[0];
				for ($i=1; $i<$numSlots+1; $i++) {
					$newSlots[$i] = array("id" => $i, "name" => $_POST["slot$i"]);
				}

				$this->getData()->setSlots($slots, $newSlots, $members);

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
		echo "<div><b>Aktive Gruppe &auml;ndern:</b> (Achtung: &Auml;nderung entfernt die Information, wer schon dran war)<br/>\n";
		echo "<select name=\"asg\"><option value=\"0\">(keine &Auml;nderung)</option>\n";
		foreach ($instruments as $iid => $iname) {
			echo "<option value=\"$iid\">$iname</option>\n";
		}
		echo "</select></div>\n";

		echo "&#160;<br/>\n";
		echo "<div><b>Anzahl Slots:</b> (Achtung: &Auml;nderung entfernt die derzeitige Stimmbildungs-Planung, danach bitte neu zusammenstellen)<br/>\n";
		echo "<select name=\"numSlots\"><option value=\"0\">(keine &Auml;nderung)</option>\n";
		for ($i=1; $i<12; $i++) {
			echo "<option value=\"$i\">$i</option>\n";
		}
		echo "</select></div>\n";

		echo "&#160;<br/>\n";
		echo "<div><b>Slotbenennung:</b><br/>";
		echo "<table cellpadding=2>";
		for ($i=1; $i<=12; $i++) {
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
}
?>
