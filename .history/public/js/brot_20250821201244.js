
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
