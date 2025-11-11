<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ROM Meta API - Search</title>
</head>
<body>
    <h1>ROM Meta API Search</h1>
    <form id="searchForm">
        <input
            type="text"
            id="searchInput"
            placeholder="Enter game name, CRC, MD5, SHA1, or Serial..."
            autofocus
        >
        <button type="submit">Search</button>
    </form>
    <pre id="result"></pre>

    <script>
        document.getElementById('searchForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const search = document.getElementById('searchInput').value;
            const resultEl = document.getElementById('result');

            if (!search) {
                resultEl.textContent = 'Please enter a search term';
                return;
            }

            resultEl.textContent = 'Loading...';

            try {
                const response = await fetch(`/api/games?search=${encodeURIComponent(search)}`);
                const data = await response.json();
                resultEl.textContent = JSON.stringify(data, null, 2);
            } catch (error) {
                resultEl.textContent = `Error: ${error.message}`;
            }
        });
    </script>
</body>
</html>
