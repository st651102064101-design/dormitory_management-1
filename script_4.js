
        // Ensure theme class is applied even if server-side didn't output it
        (function() {
            var c = 'live-light';
            if (c) {
                document.documentElement.classList.add(c);
                document.body.classList.add(c);
            }
        })();
    