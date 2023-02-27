# How to use bulk batch delete module.

Following steps will be helpfull to use this module

## Process to delete records
    - Visit to the url "bulk/delete"
    - Choose entity from dropdown which you want to delete
    - Select sub bundles in next dropdown which get populates on the basis of entity dropdown
    - Enter number of records to delete
    - Add batch size which decide number of records process in each batch
    - Select batch name, this will be helpfull to keep tracking of log 
    files and consitant name for batch.
    eg. aug24_b1_1000 (aug24(month and day), b1 (today batch number), count)

### Pros:
    - For now any user who has can set this cron. Will make it admin restricted in future versions.
    - Easy to kick start the bulk deletion.
    - Progress bar show progress as well as updated numbers.

### Cons:
    - If seession expires, batch get interrupted.
    - You need to run batch from UI and keep your machine running.
    - Many log files get created, which creates complexities while
    analysing the data.

