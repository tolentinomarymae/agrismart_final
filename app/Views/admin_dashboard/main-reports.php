<div class="main_content_iner ">
    <div class="container-fluid p-3">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="QA_section">
                    <div class="white_box_tittle list_header">
                        <h3 style="color:#88c431">Agricultural Reports</h3>
                        <div class=" box_right d-flex lms_block">
                            <div class="serach_field_2">
                                <div class="search_inner">
                                    <form method="post" action="/searchreports">
                                        <div class="search_field">
                                            <input type="text" name="search_term" placeholder="Search Reports About...">
                                        </div>
                                        <button type="submit"> <i class="ti-search"></i> </button>
                                    </form>
                                </div>
                            </div>
                            <div class="add_button ms-2">
                                <a href="#" data-bs-toggle="modal" data-bs-target="#addreportsmodal" class="btn btn-primary"><i class="fa-solid fa-plus"></i></a>
                                <a href="/adreports" class="btn btn-primary"><i class="fa-solid fa-arrows-rotate"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="QA_table mb_30">
                        <table class="table lms_table_active">
                            <thead>
                                <tr>
                                    <th scope="col">Title</th>
                                    <th scope="col">Date Reported</th>
                                    <th scope="col">Report Description</th>
                                    <th scope="col">Images</th>
                                    <th scope="col">Validity</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <?php
                            function truncateText($text, $maxLength)
                            {
                                if (strlen($text) > $maxLength) {
                                    $text = substr($text, 0, $maxLength) . '...';
                                }
                                return $text;
                            }
                            ?>
                            <tbody>
                                <?php foreach ($reports as $rep) : ?>
                                    <tr>
                                        <td><?= addslashes($rep['title']) ?></td>
                                        <td><?= $rep['created_at'] ?></td>
                                        <td><?= addslashes(truncateText($rep['description'], 100)) ?></td>
                                        <td><img src="<?= base_url() . $rep['images'] ?>" alt="" style="display: block; margin: 0 auto; width: 200px; height: 200px;"></td>
                                        <td><?= $rep['validity'] ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="background-color: #88c431; border: none;">
                                                    Actions
                                                </button>
                                                <div class="dropdown-menu">
                                                    <button class="dropdown-item" onclick="openEditReportsModal(
                                                        '<?= $rep['report_id']; ?>',
                                                        '<?= addslashes($rep['title']); ?>', 
                                                        '<?= addslashes($rep['description']); ?>',  
                                                        '<?= $rep['validity']; ?>',
                                                    )">Edit</button>
                                                    <button class="dropdown-item" onclick="deletereport(<?= $rep['report_id']; ?>)">Delete</button>
                                                    <button class="dropdown-item" onclick="openAddReportModal(
                                                        '<?= $rep['report_id']; ?>', 
                                                        '<?= addslashes($rep['title']); ?>', 
                                                        '<?= $rep['created_at']; ?>',
                                                        '<?= addslashes($rep['description']); ?>',
                                                        '<?= $rep['images']; ?>', 
                                                        '<?= $rep['validity']; ?>'
                                                    )">Send Report</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addreportsmodal" role="dialog" aria-labelledby="addreportsmodalLabel" aria-hidden="true">
    <br>
    <div class="modal-dialog modal-dialog-centered" style="z-index: 10000;">

        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addreportsmodalLabel">Send Reports</h5>
            </div>
            <div class="modal-body">
                <form action="/addreport" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" name="title" id="title" placeholder="Title" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Report Description</label>
                        <textarea type="text" name="description" id="description" placeholder="Report Description" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="images" class="form-label">Images</label>
                        <input type="file" name="images" id="images" placeholder="Report Image" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="validity" class="form-label">Report Validation</label>
                        <div class="form-input">
                            <select class="form-select mb-3" name="validity" tabindex="10" required>

                                <option value="pending">pending</option>
                                <option value="validated">validate</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
<!-- Edit Modal -->
<div class="modal fade" id="editreportsmodal" tabindex="-1" aria-labelledby="editreportsmodalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editreportsmodalLabel">Edit Reports</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="/reports/update" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="report_id" id="editreport_id">
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Title</label>
                        <input type="text" name="title" id="edit_title" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Report Description</label>
                        <textarea type="text" name="description" id="edit_description" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_validity" class="form-label">Report Validation</label>
                        <div class="form-input">
                            <select class="form-select mb-3" name="validity" id="edit_validity" tabindex="10" required>
                                <option value="pending">pending</option>
                                <option value="validated">validate</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>