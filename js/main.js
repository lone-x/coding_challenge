document.addEventListener('DOMContentLoaded', () => {
    const contestantForm = document.getElementById('contestantForm');
    const entryForm = document.querySelector('.entry-form');
    const waitingArea = document.querySelector('.waiting-area');
    const countdownDiv = document.querySelector('.countdown');
    const countdownNumber = document.querySelector('.countdown-number');
    const contestantsList = document.getElementById('contestantsList');

    let pollingInterval = null;
    
    // Add debug display
    const debugDiv = document.createElement('div');
    debugDiv.style.position = 'fixed';
    debugDiv.style.bottom = '10px';
    debugDiv.style.left = '10px';
    debugDiv.style.background = 'rgba(0,0,0,0.8)';
    debugDiv.style.color = 'white';
    debugDiv.style.padding = '10px';
    debugDiv.style.borderRadius = '5px';
    debugDiv.style.fontSize = '12px';
    document.body.appendChild(debugDiv);

    // Handle form submission
    contestantForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('username').value;
        
        try {
            const response = await fetch('register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ username })
            });

            const data = await response.json();
            console.log('Registration response:', data);

            if (response.ok && data.success) {
                entryForm.style.display = 'none';
                waitingArea.style.display = 'block';
                startPolling();
            } else {
                throw new Error(data.error || 'Registration failed');
            }
        } catch (error) {
            console.error('Registration error:', error);
            alert('Error registering: ' + error.message);
        }
    });

    // Poll for competition start and contestants list
    function startPolling() {
        // Clear any existing interval
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }

        pollingInterval = setInterval(async () => {
            try {
                const response = await fetch('check_status.php');
                const data = await response.json();
                console.log('Status check response:', data);
                
                // Update debug info
                debugDiv.innerHTML = `
                    Status: ${data.status}<br>
                    Time Diff: ${data.debug.time_diff}<br>
                    File Exists: ${data.debug.file_exists}<br>
                    Current Time: ${new Date(data.debug.current_time * 1000).toISOString()}<br>
                    ${data.debug.start_time ? `Start Time: ${new Date(data.debug.start_time * 1000).toISOString()}` : ''}
                `;
                
                if (response.ok) {
                    // Update contestants list
                    if (data.contestants) {
                        contestantsList.innerHTML = data.contestants
                            .map(contestant => `<li>${contestant}</li>`)
                            .join('');
                    }

                    // Handle competition status
                    if (data.status === 'starting') {
                        console.log('Competition starting, countdown:', data.countdown);
                        waitingArea.style.display = 'none';
                        countdownDiv.style.display = 'block';
                        countdownNumber.textContent = Math.ceil(data.countdown);
                    } else if (data.status === 'started') {
                        console.log('Competition started, redirecting to level 1');
                        clearInterval(pollingInterval);
                        window.location.href = 'level1.php';
                    }
                } else {
                    throw new Error(data.error || 'Status check failed');
                }
            } catch (error) {
                console.error('Status check error:', error);
                debugDiv.innerHTML += `<br>Error: ${error.message}`;
            }
        }, 250); // Poll every 250ms for even smoother countdown
    }
});
