<x-app-layout>
    <x-loader />
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-20 bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="input-container">
                    <h2 class="font-bold text-xl mb-4">Upload CSV to generate reports</h2>
                    <div id="drop-zone"
                        class="border-2 border-dashed border-gray-300 p-5 text-center text-gray-500 hover:scale-[1.02] transition flex items-center flex-col hover:cursor-pointer hover:border-indigo-500/75 text-indigo-500/75 text-lg">
                        Drag and drop the file here or click to select it
                        <img src="/dragdrop.png" alt="" class="">
                    </div>

                    <input type="file" class="form-control-file"
                        accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel"
                        id="file" name="file" required hidden>
                </div>
                <div id="output-container" class="hidden">
                    <div class="w-100 flex justify-start py-4 items-center">
                        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                            onclick="window.location.reload()">Upload another file
                        </button>

                        {{-- botón para descargar el archivo --}}
                        <button id="download"
                            class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded ml-4">Download
                            report
                        </button>
                    </div>
                    <div class="w-100 flex justify-between py-4 items-end">
                        {{-- select para filtrar por persona --}}
                        <div class="relative">
                            <label for="user" class="block font-bold text-gray-700">Filter by user</label>
                            <select name="user" id="user" class="" name="users[]" multiple="multiple">
                            </select>
                        </div>

                        {{-- seleccionar tipo de reporte --}}
                        <div class="h-full flex items-center">
                            <label for="reportType" class="text-lg font-bold text-gray-700">Type of
                                report: &nbsp; </label><span id="isDaily"
                                class="text-lg text-gray-800 font-medium"></span>
                        </div>

                        <div id="first_entry" class="hidden h-full flex items-center">
                            <label for="first_entry_input" class="block text-lg font-bold text-gray-700">First
                                Entry: &nbsp;</label>
                            <input type="checkbox" name="first_entry" id="first_entry_input" class="">
                        </div>


                        {{-- botón para limpiar el filtro --}}
                        <div class="h-full  flex items-center">
                            <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded"
                                id="clearFiltro">Clean filters
                            </button>
                        </div>
                    </div>
                    <div>
                        <table id="table" class="" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Time</th>
                                    <th>Department</th>
                                    <th>Verification Mode</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file');
        const form = document.getElementById('upload-form');
        const inputContainer = document.querySelector('.input-container');
        const outputContainer = document.getElementById('output-container');
        var isDaily = true;
        var firstEntry = false;

        dropZone.addEventListener('click', () => {
            fileInput.click();
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');

        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            validateFile();
        });

        fileInput.addEventListener('change', () => {
            validateFile();
        });

        function validateFile() {
            var file = fileInput.files[0];
            var fileName = fileInput.files[0].name;
            var fileExtension = fileName.split('.').pop().toLowerCase();

            if (!file) {
                Swal.fire({
                    icon: "error",
                    title: "Oops...",
                    text: "Enter a file!"
                });
                return;
            }

            if (fileInput.files.length > 1) {
                Swal.fire({
                    icon: "warning",
                    title: "Oops...",
                    text: "You can only enter one file!"
                });
                // limpiar el input
                input.value = '';
                return;
            }

            // validar tamaño del archivo que no sea mayor a 10MB
            if (file.size > 10000000) {
                Swal.fire({
                    icon: "warning",
                    title: "Oops...",
                    text: "The file is too large! 10MB max."
                });
                input.value = '';
                return;
            }

            if (fileExtension !== 'csv') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'You can only enter .csv type files.!'
                });
                input.value = '';
            }

            submitForm();
        }

        function submitForm() {
            startLoading();
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('_token', '{{ csrf_token() }}');
            // Send with ajax
            $.ajax({
                url: '/upload-csv',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    inputContainer.classList.add('hidden');
                    outputContainer.classList.remove('hidden');
                    $('#table').DataTable({
                        data: response.data,
                        columns: [{
                                data: 'user_id'
                            },
                            {
                                data: function(row) {
                                    return row.nombre + ' ' + row.apellido;
                                }
                            },
                            {
                                data: 'tiempo'
                            },
                            {
                                data: 'dpto'
                            },
                            {
                                data: 'verificacion'
                            }
                        ]
                    });

                    // llenar el select con los usuarios
                    var users = response.data.map(function(item) {
                        return {
                            id: item.user_id,
                            name: item.nombre + ' ' + item.apellido
                        };
                    });
                    var uniqueUsers = [...new Map(users.map(item => [item['id'], item])).values()];
                    var select = document.getElementById('user');
                    uniqueUsers.forEach(function(user) {
                        var option = document.createElement('option');
                        option.value = user.id;
                        option.text = user.name;
                        select.appendChild(option);
                    });
                    // inicializar select2
                    $('#user').select2({
                        placeholder: 'Filter by user'
                    });

                    // colorar el tipo de reporte
                    isDaily = response.isDaily;
                    var type = isDaily ? 'Daily' : 'Weekly';
                    document.getElementById('isDaily').textContent = type;

                    // si es Daily, mostrar el checkbox para filtrar por primera entrada
                    if (response.isDaily) {
                        document.getElementById('first_entry').classList.remove('hidden');
                        //agregra el evento change al checkbox
                        document.getElementById('first_entry_input').addEventListener('change',
                            function() {
                                firstEntry = this.checked;
                            });
                    }

                    stopLoading();
                },
                error: function(error) {
                    console.error('Error:', error);
                    stopLoading();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while uploading the file!'
                    });
                }
            });
        }

        // seleccionar una persona
        $('#user').on('change', function() {
            var selectedUsers = $('#user').select2('data');
            var selectedUserIds = selectedUsers.map(function(user) {
                return user.id;
            });
            // si hay usuarios seleccionados, deshabilitar la búsqueda
            $('#table').DataTable().search(selectedUserIds.join('|'), true, false).draw();

        });

        // descargar archivo
        $('#download').click(function() {
            startLoading();
            // obtener la data del datatable en una archivo .csv
            var data = $('#table').DataTable().rows({
                filter: 'applied'
            }).data().toArray();

            //     $.ajax({
            //         url: '/download-excel',
            //         type: 'POST',
            //         // headers: {
            //         //     'Content-Type': 'application/json',
            //         //     'Accept': 'application/json',
            //         //     'X-CSRF-TOKEN': '{{ csrf_token() }}'
            //         // },
            //         data: {
            //             data: data,
            //             _token: '{{ csrf_token() }}'
            //         },
            //         success: function(response) {
            //             var blob = new Blob([response], {
            //                 // type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            //                 type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=UTF-8'
            //             });
            //             console.log(blob);
            //             const url = window.URL.createObjectURL(new Blob([blob]));
            //             console.log(url);
            //             const link = document.createElement('a');
            //             link.href = url;
            //             link.setAttribute('download', 'ReporteSemanal-' + new Date()
            //                 .toISOString()
            //                 .slice(0, 10) + '.xlsx');
            //             document.body.appendChild(link);
            //             link.click();
            //             link.parentNode.removeChild(link);
            //             stopLoading();
            //             Swal.fire({
            //                 icon: 'success',
            //                 title: 'Success',
            //                 text: 'File downloaded successfully!'
            //             });
            //         },
            //         error: function(error) {
            //             console.error('Error:', error);
            //             stopLoading();
            //             Swal.fire({
            //                 icon: 'error',
            //                 title: 'Error',
            //                 text: 'An error occurred while downloading the file!'
            //             });
            //         }
            //     });

            // use fetch API to download the file
            fetch('/download-excel', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        data: data,
                        isDaily: isDaily,
                        firstEntry: firstEntry
                    })
                })
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(new Blob([blob]));
                    const link = document.createElement('a');
                    const type = isDaily ? 'ReporteDiario' : 'ReporteSemanal';
                    const filename = type + '-' + new Date().toISOString()
                        .slice(0, 10) + '.xlsx';
                    console.log(filename);
                    link.href = url;
                    link.setAttribute('download', filename);
                    document.body.appendChild(link);
                    link.click();
                    link.parentNode.removeChild(link);
                    stopLoading();
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'File downloaded successfully!'
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    stopLoading();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while downloading the file!'
                    });
                });

        });

        $('#clearFiltro').click(function() {
            $('#user').val(null).trigger('change');
            $('#table').DataTable().search('').draw();
        });

    }); // end DOMContentLoaded
</script>
