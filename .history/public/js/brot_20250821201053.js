
$('#search').typeahead({
    minLength: 2,
    highlight: true,
    source: function(query, process) {
        $.ajax({
            url: 'suggest.php', // Pfad bleibt gleich, da suggest.php in public/
            data: { query: query },
            dataType: 'json',
            success: function(data) {
                process(data);
            },
            error: function(xhr, status, error) {
                console.error('Fehler bei der Autovervollständigung:', error);
                process([]);
            }
        });
    },
    updater: function(item) {
        $('#search-form').submit();
        return item;
    }
});

$('#search-form').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: 'search.php', // Pfad bleibt gleich, da search.php in public/
        data: $(this).serialize(),
        dataType: 'json',
        success: function(data) {
            $('#results').empty();
            data.forEach(function(item) {
                $('#results').append(`
                    <div class="recipe-card">
                        <img src="${item.image}" alt="${item.name}">
                        <h3>${item.name}</h3>
                        <p>${item.description_short}</p>
                    </div>
                `);
            });
        },
        error: function(xhr, status, error) {
            console.error('Fehler bei der Suche:', error);
            $('#results').html('<p>Fehler beim Laden der Ergebnisse.</p>');
        }
    });
});

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
