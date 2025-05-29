// Function to load break records into the form
function loadLiveBreakRecordsExternal(breaks) {
    const container = document.getElementById('liveBreakRecordsContainer');
    container.innerHTML = ''; // Clear loading message
    
    if (!breaks || breaks.length === 0) {
        container.innerHTML = '<div class="no-breaks-message">No break records found</div>';
        return;
    }
    
    // Create a table for break records
    const table = document.createElement('table');
    table.className = 'break-records-table';
    table.innerHTML = `
        <thead>
            <tr>
                <th>Break In</th>
                <th>Break Out</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    `;
    
    const tbody = table.querySelector('tbody');
    
    // Add each break record
    breaks.forEach((breakRecord, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <input type="time" name="break_start[]" value="${formatTimeForInput(breakRecord.break_in)}" required>
            </td>
            <td>
                <input type="time" name="break_end[]" value="${formatTimeForInput(breakRecord.break_out)}">
            </td>
            <td>
                <input type="hidden" name="break_id[]" value="${breakRecord.id || 'new'}">
                <button type="button" class="delete-break-btn" onclick="deleteBreakRecord(this)">Delete</button>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Add "Add Break" button
    const addBreakBtn = document.createElement('button');
    addBreakBtn.type = 'button';
    addBreakBtn.className = 'add-break-btn';
    addBreakBtn.textContent = 'Add Break';
    addBreakBtn.onclick = addNewBreakRecord;
    
    container.appendChild(table);
    container.appendChild(addBreakBtn);
}

// Helper function to format time for input[type="time"]
function formatTimeForInput(timeString) {
    if (!timeString) return '';
    const date = new Date(timeString);
    return date.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' });
}

// Function to add a new break record row
function addNewBreakRecord() {
    const tbody = document.querySelector('#liveBreakRecordsContainer table tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            <input type="time" name="break_start[]" required>
        </td>
        <td>
            <input type="time" name="break_end[]">
        </td>
        <td>
            <input type="hidden" name="break_id[]" value="new">
            <button type="button" class="delete-break-btn" onclick="deleteBreakRecord(this)">Delete</button>
        </td>
    `;
    tbody.appendChild(row);
}

// Function to delete a break record row
function deleteBreakRecord(button) {
    const row = button.closest('tr');
    row.remove();
}

// Function to open the Live Time Editor modal
function openLiveTimeEditorModal(username) {
    // Show the modal
    const liveTimeEditorModal = document.getElementById('liveTimeEditorModal');
    liveTimeEditorModal.style.display = 'block';
    
    // Set the employee name
    document.getElementById('liveTimeEditorEmployee').textContent = 'Editing time record for: ' + username;
    document.getElementById('liveEditEmployeeUsername').value = username;
    
    // Clear previous form data
    document.getElementById('liveEditNotes').value = '';
    document.getElementById('liveEditTimeRecordId').value = '';  // Clear ID first
    const liveBreakRecordsContainer = document.getElementById('liveBreakRecordsContainer');
    liveBreakRecordsContainer.innerHTML = '<div class="loading-message">Loading break records...</div>';
    
    // Fetch the current time record for the employee
    fetch('get_live_time_record.php?username=' + encodeURIComponent(username))
        .then(response => {
            if (!response.ok) {
                throw new Error('Server returned status ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (!data.timeRecord || !data.timeRecord.id) {
                    throw new Error('No active time record found for this employee');
                }
                
                // Set time record ID
                document.getElementById('liveEditTimeRecordId').value = data.timeRecord.id;
                console.log("Setting time record ID to:", data.timeRecord.id);
                
                // Use the time directly - server returns Eastern Time
                // The clock_in should already be in Eastern Time from the server
                let clockInTime = data.timeRecord.clock_in;
                if (clockInTime && clockInTime.includes(' ')) {
                    // If the time includes date information, extract just the time portion
                    clockInTime = clockInTime.split(' ')[1].substring(0, 5);
                }
                document.getElementById('liveEditClockIn').value = clockInTime || '';
                
                // Load break records
                loadLiveBreakRecordsExternal(data.timeRecord.breaks || []);
            } else {
                throw new Error(data.message || 'Could not load time record');
            }
        })
        .catch(error => {
            liveBreakRecordsContainer.innerHTML = '<div class="error-message">Error: ' + error.message + '</div>';
            alert('Error: ' + error.message + '\nPlease make sure the employee has an active clock-in session.');
            liveTimeEditorModal.style.display = 'none'; // Close the modal on error
        });
} 