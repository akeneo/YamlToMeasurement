# YamlToMeasurement
> Migrate data from [MeasureBundle](https://github.com/akeneo/MeasureBundle).

# Installation

```bash
git clone git@github.com:akeneo/YamlToMeasurement.git yaml_to_measurement
cd yaml_to_measurement
composer install
```

# Setup
Note: to setup this tool, you'll need a valid **API Client ID** and its **API Client secret** from your Akeneo PIM instance. Read the dedicated documentation to proceed: https://api.akeneo.com/getting-started-admin.html

Back in tool, you need to copy the [.env](https://symfony.com/doc/current/components/dotenv.html) file:
```bash
cp .env .env.local
```

Then open `.env.local` to define the needed configuration vars:
```
AKENEO_API_BASE_URI=http://your-akeneo-pim-instance.com
AKENEO_API_CLIENT_ID=123456789abcdefghijklmnopqrstuvwxyz
AKENEO_API_CLIENT_SECRET=123456789abcdefghijklmnopqrstuvwxyz
AKENEO_API_USERNAME=admin
AKENEO_API_PASSWORD=admin
```

# How to Use

## Import your file

take your measure.yml file and launch the following command:
```bash
php bin/console app:import <yaml_file_path>
```

So if you want to import your measurement families in the .yml file located in `/tmp/measure.yml`:
```bash
php bin/console app:import /tmp/measure.yml
```
