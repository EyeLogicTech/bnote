/*
Dieses Skript initialisiert alle Tabellen (sofern nötig)
und fügt einen Zähler „Aufgerufen: X“ rechts neben dem Filterfeld ein.
author: hL
*/

document.addEventListener("DOMContentLoaded", function () {
	document.querySelectorAll("table").forEach((table) => {
		const tableId = table.getAttribute("id");
		if (!tableId) return;

		const $table = $("#" + tableId);

		let dataTable;
		if (!$.fn.DataTable.isDataTable($table)) {
			// Nur initialisieren, wenn noch nicht geschehen
			dataTable = $table.DataTable({
				paging: false,
				info: false,
				responsive: true
				// Sprache muss ggf. lokal im PHP-Code gesetzt werden
			});
		} else {
			dataTable = $table.DataTable();
		}

		const $label = $("#" + tableId + "_filter label");
		if (!$label.length) return;

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
	});
});