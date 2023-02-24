/*jshint esversion: 6 */
((settings) => {
        document.getElementById('edit-days').addEventListener('change', (event) => {
            document.querySelectorAll('#edit-calendar-table tr').forEach((tr) => {
                let td = tr.querySelector('td')
                if (td) {
                    if (event.target.value === 'all') {
                        tr.classList.remove('hidden')
                    } else {
                        if (td.innerText === event.target.value) {
                            tr.classList.remove('hidden')
                        } else {
                            tr.classList.add('hidden')
                        }
                    }
                }
            })
        })
    }
)(drupalSettings)
