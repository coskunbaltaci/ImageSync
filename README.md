
* Read this in other languages: [Turkish](README.tr.md)
# ImageSync

The Image Sync module has been developed for Magento 2. It allows adding more than one image to the products in bulk via any FTP address.

# How does it work?

You have to upload to FTP Which list of products and images. The list will be read from FTP and save to DB. Then this list will start to be processed by manual triggering or by cron.

Process logs are kept in "***var/log/cb_image_sync.lo**g*" file. When the process is finished, it is added to the file specified at the FTP address. In addition, the log file created as a result of the process is sent to the specified e-mail address.

Successful records are deleted with cron.

# Installation

- Create "***Cb***" folder under "***App/Code***" and add "ImageSync" module into it.

- Run "`bin/php magento setup:upgrade`".

- Run "`bin/php magento cache:clean`".

- After the module is installed, the module should be activated from the settings that will come under "***Stores/Configuration***" in the admin panel and the settings should be completed.

# Settings

Module settings can be made under the "***CB Settings***" menu under "***Store/Configuration***".

## "General"

**Enable**: The module can be turned on or off from the admin panel.

**Use Custom Format**: We can choose whether images are to be uploaded from FTP and the product list will consist of CSV file or image name.

-  **"yes"**: If the Yes option is selected, the "sample.csv" file in the explanation text is filled in the same format and sent to FTP.

-  **"No"**: If No option is selected, a list is created according to the image name. Image names should be in the format below.

Example: productsku-order.jpg -> exampleSku-1.jpg

**Use Product Name as Alt tag**: We can choose whether or not the product name should be used in the footer of the image.

**Image Import Frequency**: Specifying the "Cron" run frequency created for importing images. Example usage: */5 * * * *

**Import row count**: Number of rows to be processed when a transaction runs

**Delete Done Rows Frequency**: Specifying the "Cron" run frequency created to delete successful rows. Example usage: */5 * * * *

**Log Emails**: The e-mail addresses to which the log file will be sent at the end of the process. Multiple addresses can be separated using commas.

## "FTP"

**Host** : FTP address

**Username** : FTP username

**Password** : FTP user password

**Path**: The name of the folder where the CSV and images to be uploaded will be placed. The full path of the FTP user must be written. Example: /ftp/username/folder-name/uploads/

**Success Path**: The name of the folder where successfully uploaded files will be moved. The full path of the FTP user must be written. Example: /ftp/username/folder-name/success-foldder/

**Logs Path**: The name of the log file to be kept by FTP. The full path of the FTP user must be written. Example: /ftp/username/folder-name/logs/

# How do I upload images?

After the settings are made from the admin panel, CSV files and images are uploaded to the uploads path in FTP

Go to the "ImageSync Index" page under the "Cb Modules" menu.

Firstly you have to create import list by clicking the "Get Import List" button

Then you may execute the list. The following three methods can be used to run the list:

1. Operation of the list according to the Cron frequency defined in the settings can be expected.

2. The process can be started manually by pressing the "Start Image Capture" button without waiting for the cron.

3. The process can be started using the following command on the server

`bin/magento cb:image-sync:start`

# Additional information

All operations can be performed on the server using the commands below.

| command | Description |
|--|--|
| cb:image-sync:create-folder | Creates folders on server |
| cb:image-sync:delete-done-rows | Deletes successful rows |
| cb:image-sync:save-import-list | Saves import list to db |
| cb:image-sync:start | Starts processing the import list |

# Licence
It is free software distributed under the terms of the MIT license.

# Communication
In case of any problem, you may open a task or send an email.