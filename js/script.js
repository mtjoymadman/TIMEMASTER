setInterval(() => {
    let shift = parseFloat(document.getElementById('shift').innerText);
    if (shift > 0) {
        shift += 1 / 3600;
        document.getElementById('shift').innerText = shift.toFixed(2) + ' hours';
    }
}, 1000);