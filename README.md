# Domain Registrar API Integration

This project supports downloading domain information from various domain registrars using their APIs.

## Supported Registrars

### Dynadot
- API Documentation: https://www.dynadot.com/domain/api3.html
- Capabilities:
  - Download domain list
  - Domain information retrieval

### GOdaddy docs
- API: https://developer.godaddy.com/doc/endpoint/domains#/v1/list


### Other Registrars
- Cosmotown
- GoDaddy
- Namebright
- Namecheap
- Name.com
- Porkbun
- Regery
- SAV
- Spaceship

## Getting Started
1. Obtain API credentials from your registrar
2. Configure your API keys in the settings
3. Use the download command to retrieve your domains


### API DOWNLOAD COMPLETITION STATUS
 -dynadot DONE
 -godaddy DONE
- Cosmotown - no api
- Namebright - requires 100$
- Namecheap DONE
- Name.com DONE
- Porkbun DONE
- Regery - rare
- SAV - whitelist ips 
- Spaceship DONE


### CRONTAB DOWNLOAD ALL REGISTRARS
crontab -e
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1