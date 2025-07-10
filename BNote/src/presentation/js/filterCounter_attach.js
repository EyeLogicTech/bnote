/*
Wird in src/presentation/widgets/table.php aufgerufen. Fügt dem bereits initialisierten DataTable einen Zähler „Anzahl: X“ hinzu.
author: hL
*/

function setupFilterCounter(tableId) {
	const $table = $("#" + tableId);

	// Sicherstellen, dass es eine DataTable ist
	if (!$.fn.DataTable.isDataTable($table)) {
		return;
	}

	const dataTable = $table.DataTable();
	const $label = $("#" + tableId + "_filter label");
	if (!$label.length) return;

	// Verhindern, dass der Zähler mehrfach hinzugefügt wird
	if (document.getElementById(tableId + "_counter")) return;

	const counter = document.createElement("span");
	counter.id = tableId + "_counter";
	counter.style.marginLeft = "10px";
	counter.style.fontWeight = "normal";
	$label.append(counter);

	function updateCounter() {
		const count = dataTable.rows({ filter: 'applied' }).count();
		counter.textContent = "Anzahl: " + count;
	}

	updateCounter();
	dataTable.on('search.dt', updateCounter);
}