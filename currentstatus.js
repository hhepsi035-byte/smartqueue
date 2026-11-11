document.addEventListener("DOMContentLoaded", function() {
    function updateLiveStats() {
        fetch('get_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 1. Update Currently Serving Token (A/B Pools)
                    const tokenEl = document.querySelector('.token-number');
                    if (tokenEl) {
                        tokenEl.innerText = data.current_token;
                    }

                    // 2. Update Stats Bar (Appointments, Departments, Waiting Time, Citizens Served)
                    const h3List = document.querySelectorAll('.stats-bar .stat-box h3');
                    if (h3List.length >= 4) {
                        h3List[0].innerText = data.appointments;
                        h3List[1].innerText = data.departments;
                        h3List[2].innerText = data.waiting_time;
                        h3List[3].innerText = data.citizens_served; // Updates Citizens Served here!
                    }

                    // 3. Update People Waiting and Estimated Wait (30 mins per person)
                    const statItems = document.querySelectorAll('.queue-stats .stat-item');
                    if (statItems.length >= 2) {
                        const waitingStrong = statItems[0].querySelector('strong');
                        if (waitingStrong) {
                            waitingStrong.innerText = data.people_waiting;
                        }
                        
                        const waitTimeStrong = statItems[1].querySelector('strong');
                        if (waitTimeStrong) {
                            let totalMinutes = parseInt(data.people_waiting) * 30;
                            waitTimeStrong.innerText = totalMinutes + " min";
                        }
                    }
                }
            })
            .catch(err => console.log('Stats error:', err));
    }

    // Run immediately on load, then auto-poll every 3 seconds
    updateLiveStats();
    setInterval(updateLiveStats, 3000);
});