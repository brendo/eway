# eWay Payment Gateway

- Version: 0.1
- Release Date: 14th December 2011
- Author: Brendan Abbott
- Requirements: Symphony 2.2

An interface for eWay's Merchant Hosted Payments CVN API. Developers can use this to process payments from custom events. This extension also includes a Payment Gateway interface that hooks in with the [PGI Loader](https://github.com/brendo/pgi_loader)

## Installation

1. Upload the `eway` folder to your Symphony `/extensions` folder.

2. Enable the 'eWay' extension on the extensions page

3. Go to System > Preferences to add your Customer ID and set your Gateway Mode. For testing it's recommended to leave the Gateway as development which will use eWay's test Gateway & Customer ID information.

4. Have a read of the [API](https://github.com/brendo/eway/wiki/API-Reference)

## Credits

Extension is largely based on previous work by Henry Singleton and his Secure Pay extension, I've taken it an applied some paint for Symphony 2.2 and hope to expand to include other eWay API products.