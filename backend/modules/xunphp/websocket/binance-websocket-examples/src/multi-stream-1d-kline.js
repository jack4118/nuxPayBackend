#!/usr/bin/env node

import logger from './lib/logger';
import SocketClient from './lib/socketClient';

export default async function createApp() {
  logger.info('Start application');

  let pairs = [
    'ethbtc',
    'btcusdt',
    'ethusdt',
    'ltcusdt',
    'bchusdt',
    'filusdt'
  ];
    
  pairs = pairs.map((pair) => `${pair}@kline_1d`).join('/');
  logger.info(pairs);

  const socketApi = new SocketClient(`stream?streams=${pairs}`);
  socketApi.setHandler('depthUpdate', (params) => logger.info(JSON.stringify(params)));
}

createApp();
