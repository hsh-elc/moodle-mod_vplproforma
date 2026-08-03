# VPL+ developer handbook

This document describes the changes that have been made to the upstream VPL plugin in order to create the VPL+ version, which allows teachers to upload ProFormA tasks directly to VPL.

### The submission form

Core modification of the plugin is the new [proforma_submission.php](../forms/proforma_submission.php), which contains the `mod_vpl_proforma_submission_form` class. This class contains the moodle form definition for submitting the ProFormA task, as well as the core logic for preparing the task file, setting the necessary VPL execution files, setting VPL instance parameters and pulling the VPL-ProFormA-Integration from GitHub.

This form is linked inside [executionoptions.php](../forms/executionoptions.php) from where it can be reached.

### Helper classes

The `mod_vpl_proforma_submission_form` class uses three helper classes that provide functionality for managing files, fetching VPL-ProFormA-Integration releases from GitHub and extracting task metadata from the ProFormA task file.

- `proforma_file_manager` found in [file_manager.php](../classes/proforma/file_manager.php) is a facade over VPL's 'execution' and 'required' file group managers. It provides methods for easily managing VPL's files from a single interface.

- `proforma_task_doc` found in [task_doc.php](../classes/proforma/task_doc.php) is a helper class that takes in the raw contents of a ProFormA task file and provides methods for extracting some task metadata.

- `proforma_release_fetcher` found in [release_fetcher.php](../classes/proforma/release_fetcher.php) us a helper class that fetches available VPL-ProFormA-Integration releases and their assets from GitHub's REST API.
