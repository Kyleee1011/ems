            <div class="max-w-3xl mx-auto stat-card overflow-hidden text-center">
                <div class="p-10">
                    <div class="w-16 h-16 bg-green-50 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Bulk Employee Upload</h3>
                    <p class="text-gray-500 text-sm mb-8">Upload a CSV file to add multiple employees at once.</p>
                    <div class="flex justify-center gap-4 mb-8">
                        <a href="?action=download_template" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-50 flex items-center gap-2"><i class="fa-solid fa-download"></i> Download Template</a>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data" class="bg-gray-50 border border-dashed border-gray-300 rounded-xl p-8 hover:border-primary-500 transition-colors cursor-pointer relative">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="bulk_upload">
                        <input type="file" name="csv_file" accept=".csv" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <p class="text-sm font-bold text-gray-600">Click or drag CSV file here</p>
                        <button type="submit" class="mt-4 px-6 py-2 bg-primary text-primary-foreground rounded-lg text-sm font-bold relative z-20 hover:bg-primary-600 pointer-events-none shadow-sm">Upload File</button>
                    </form>
                </div>
            </div>
