
function createSnippet(text, query, maxLength = 100) {
    if (!text || !query) return text.substring(0, maxLength) + '...';
    const words = query.trim().split(/\s+/);
    const regex = new RegExp(`(${words.join('|')})`, 'gi');
    const match = text.match(regex);
    if (!match) return text.substring(0, maxLength) + '...';
    const pos = text.toLowerCase().indexOf(match[0].toLowerCase());
    const start = Math.max(0, pos - maxLength / 2);
    let snippet = text.substring(start, start + maxLength);
    if (start > 0) snippet = '...' + snippet;
    if (start + maxLength < text.length) snippet += '...';
    return snippet.replace(regex, '<mark>$1</mark>');
}

function roundWeight(weight) {
    weight = parseFloat(weight);
    if (weight < 1) return weight.tofixed(2);
    if (weight < 10) return weight.tofixed(1);
    return weight.toFixed(0);
}

function formatHHMM(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
}

function deepEqual(a, b) {
    if (a === b) return true; // gleiche Referenz oder primitive Werte
    if (a == null || b == null) return false; // wenn einer null/undefined ist
    if (typeof a !== 'object' || typeof b !== 'object') return console.log(2);

    const aKeys = Object.keys(a).sort();
    const bKeys = Object.keys(b).sort();
    if (aKeys.length !== bKeys.length) return console.log(3);

    for (let i = 0; i < aKeys.length; i++) {
        if (aKeys[i] !== bKeys[i]) return console.log(1);
        if (!deepEqual(a[aKeys[i]], b[bKeys[i]])) return console.log(4);
    }

    return true;
}

function formatMinutesToHoursDays(mins) {
    if (mins < 60 * 23.5) {
        const hours = new Intl.NumberFormat('de-DE').format(
            Math.round(mins / 60) / 1
        );
        return hours + "&nbsp;Stunde" + (hours === "1" ? "" : "n");
    }
    const days = new Intl.NumberFormat('de-DE').format(
        Math.round(mins / 720) / 2 // 1 Tag = 1440 Minuten
    );
    return days + "&nbsp;Tag" + (days === "1" ? "" : "e");
    //   let dayString = '';
    //   if (days > 1) dayString = days + ' Tage ';
    //   else if (days === 1) dayString = days + ' Tag ';
    //   else dayString = '';
    //   const h = Math.floor((mins % 1440) / 60);
    //   const m = mins % 60;
    //   return `${dayString}${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}`;
}

function spaces(str) {
    return str.replace(/(\d+) %/g, "$1\u{202F}%");
}

function indexReady() {
    $(document).ready(function () {
        let debounceTimer;
        let page = 1;
        const perPage = 12;
        let prevResponse = null;

        document.addEventListener('click', function (e) {
            // Prüfen, ob ein Eltern-Card-Element geklickt wurde
            const card = e.target.closest('.card');
            if (card && card.dataset.url) {
                window.location.href = card.dataset.url;
            }
        });

        // Select2 initialisieren
        $('#ingredientFilter').select2({
            theme: 'bootstrap-5',
            placeholder: "Zutaten auswählen...",
            allowClear: true,
            selectionAdapter: $.fn.select2.amd.require("select2/selection/customSelectionAdapter"),
            selectionContainer: $('#selectedIngredients')
            // dropdownParent: $('.container')
        });

        // // Bloodhound für Typeahead.js
        // var recipes = new Bloodhound({
        //     datumTokenizer: function (d) {
        //         return Bloodhound.tokenizers.whitespace(d.name);
        //     },
        //     queryTokenizer: Bloodhound.tokenizers.whitespace,
        //     remote: {
        //         url: 'suggest.php?q=%QUERY',
        //         wildcard: '%QUERY',
        //         transform: function (response) {
        //             console.log('Bloodhound response:', response);

        //             // Optional: auf 50 Vorschläge begrenzen
        //             return Array.isArray(response) ? response.slice(0, 50) : [];
        //         }
        //     }
        // });

        // // Initialisieren (async nötig bei Bloodhound 0.11+)
        // recipes.initialize();

        // // Typeahead initialisieren
        // $('#searchInput').typeahead({
        //     hint: true,
        //     highlight: true,
        //     minLength: 1
        // }, {
        //     name: 'recipes',
        //     // display: 'name',      // Feld für die Anzeige
        //     source: recipes,      // Bloodhound direkt als Source
        //     limit: 50,            // Limit größer setzen als maximal erwartete Ergebnisse
        //     templates: {
        //         empty: '<div class="tt-suggestion p-2">Keine Vorschläge gefunden</div>',
        //         suggestion: function (data) {
        //             return '<div class="tt-suggestion p-2">' + data + '</div>';
        //         }
        //     }
        // }).on('typeahead:select', function (event, suggestion) {
        //     console.log('Ausgewählt:', suggestion);
        //     $('#searchInput').val(suggestion); // nur den String ins Feld
        //     performSearch(); // Deine Suchfunktion
        // });

        $('#ingredientFilter').on('change.select2', function (e) {
            if ($('#ingredientFilter').val().length > 1) {
                console.log('Multiple ingredients selected');
                $('#ingredientLogic').removeClass('d-none');
            } else {
                console.log('Single ingredient selected');
                $('#ingredientLogic').addClass('d-none');
            }
        });
        $('#searchInput, #ingredientFilter, input[name="ingredientLogic"]').on('input change', function () {
            const queryLength = $('#searchInput').val().trim().length;
            if (queryLength && queryLength < 4 && $('#ingredientFilter').val().length === 0) {
                $('#alert-condition').removeClass('d-none');
                $('#alert-searching').hide();
                return;
            }
            if (!queryLength) {
                $('#alert-condition').addClass('d-none');
            }
            $('#alert-condition').addClass('d-none');
            $('#alert-searching').show();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(performSearch, 300);
        });

        $('#searchBtn').click(performSearch);

        $('#pagination').on('click', 'a.page-link', function (e) {
            e.preventDefault();
            page = parseInt($(this).data('page'));
            performSearch();
        });

        function escapeRegExp(str) {
            // aus MDN
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        function markTerms(text, query) {
            if (!text || !query) return text;
            const words = query.trim().split(/\s+/);
            const escaped = words
                .filter(w => w && w.length)      // leere raus
                .map(escapeRegExp)
                .join('|');
            const regex = new RegExp(`(${escaped})`, 'giu'); // g=global, i=case-insens, u=Unicode
            return text.replace(regex, '<mark>$1</mark>');
        }

        function createSnippet(text, query, maxLength = 80) {
            if (!text || !query) return text.substring(0, maxLength) + '...';
            const words = query.trim().split(/\s+/);
            const escaped = words
                .filter(w => w && w.length)      // leere raus
                .map(escapeRegExp)
                .join('|');
            const regex = new RegExp(`(${escaped})`, 'giu'); // g=global, i=case-insens, u=Unicode
            const match = text.match(regex);
            if (!match) return text.substring(0, maxLength) + '...';
            const pos = text.toLowerCase().indexOf(match[0].toLowerCase());
            const start = Math.max(0, pos - maxLength / 2);
            let snippet = text.substring(start, start + maxLength);
            if (start > 0) snippet = '...' + snippet;
            if (start + maxLength < text.length) snippet += '...';
            return snippet.replace(regex, '<mark>$1</mark>');
        }

        function performSearch() {
            const query = $('#searchInput').val().trim();
            const ingredients = $('#ingredientFilter').val() || [];
            const logic = $('input[name="ingredientLogic"]:checked').val() || 'OR';

            $('#alert-searching').show();

            // console.log('Sending AJAX:', { q: query, ingredients: ingredients, logic: logic, page: page, per_page: perPage });

            $.ajax({
                url: 'search.php',
                type: 'POST',
                data: { q: query, ingredients: ingredients, logic: logic, page: page, per_page: perPage },
                dataType: 'json',
                success: function (response) {
                    $('#alert-searching').hide();
                    if (deepEqual(prevResponse, response)) {
                    $('#loading').hide();
                        return;
                    }
                    console.log(prevResponse, response);
                    prevResponse = response;
                    $('#results').empty();
                    $('#pagination').empty();
                    if (response.error) {
                        $('#results').html('<div class="alert alert-danger" role="alert">' + response.error + '</div>');
                        return;
                    }
                    if (response.total === 0) {
                        $('#results').html('<div class="alert alert-info" role="alert">Keine Ergebnisse gefunden.</div>');
                        return;
                    }

                    response.forEach(function (item) {
                        const card = `
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                    <div class="card" aria-labelledby="card-title-${item.uebersicht_id}" data-url="detail.php?id=${item.uebersicht_id}">
                                        ${item.image ? `<img src="${item.image}" class="card-img-top" alt="${item.name}" style="max-height: 150px; object-fit: cover;">` : ''}
                                        <div class="card-body">
                                            <h5 class="card-title" id="card-title-${item.uebersicht_id}">${spaces(markTerms(item.name, query))}</h5>
                                            <h6 class="card-subtitle mb-2 text-muted">${spaces(markTerms(item.type, query)) || ''}</h6>
                                            <p class="card-text flex-grow-1 mb-2">${item.description_short ? spaces(createSnippet(item.description_short, query)) : ''}</p>
                                            <div class="d-flex d-flex justify-content-between">
                                                <div class="card-text prepTime text-secondary" data-minutes="${item.prep_minutes}">
                                                    <small>${formatMinutesToHoursDays(Math.round(item.prep_minutes / 30) * 30)}</small>
                                                </div>
                                                <div class="card-text prepTime text-secondary" data-minutes="${item.prep_minutes}">
                                                    <small>ploetzblog</small>
                                                </div>
                                                <!-- <a href="detail.php?id=${item.uebersicht_id}" class="btn btn-outline-primary btn-sm">Details</a> -->
                                            </div>
                                        </div>
                                    </div>
                                </div>`;
                        $('#results').append(card);
                    });

                    const totalPages = Math.ceil(response.total / perPage);
                    let pagination = '<nav aria-label="Seitennavigation der Suchergebnisse"><ul class="pagination justify-content-center">';
                    if (page > 1) {
                        pagination += `<li class="page-item"><a class="page-link" href="#" data-page="${page - 1}">Vorherige</a></li>`;
                    }
                    for (let i = 1; i <= totalPages; i++) {
                        pagination += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
                    }
                    if (page < totalPages) {
                        pagination += `<li class="page-item"><a class="page-link" href="#" data-page="${page + 1}">Nächste</a></li>`;
                    }
                    pagination += '</ul></nav>';
                    $('#pagination').html(pagination);
                },
                error: function (xhr, status, error) {
                    $('#loading').hide();
                    $('#results').html('<div class="alert alert-danger" role="alert">Fehler bei der Suche: ' + (xhr.statusText || 'Unbekannter Fehler') + '</div>');
                    console.error('AJAX error:', status, error, xhr.responseText);
                }
            });
        }
    });
}