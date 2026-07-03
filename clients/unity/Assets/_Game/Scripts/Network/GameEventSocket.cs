using System;
using System.Collections.Concurrent;
using System.Net.WebSockets;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using CraftWorld.Client.Network.Dto;
using Newtonsoft.Json;
using UnityEngine;

namespace CraftWorld.Client.Network
{
    /// <summary>
    /// WebSocket client for /ws game events. Dispatches on Unity main thread.
    /// </summary>
    public sealed class GameEventSocket : IDisposable
    {
        private readonly ConcurrentQueue<GameEventDto> _queue = new();
        private ClientWebSocket _socket;
        private CancellationTokenSource _cts;
        private long _lastEventId;

        public event Action<GameEventDto> OnEvent;
        public bool IsConnected => _socket?.State == WebSocketState.Open;

        public async Task ConnectAsync(GameApiConfig config, string characterUuid, long lastEventId = 0)
        {
            await DisconnectAsync();
            _lastEventId = lastEventId;
            _cts = new CancellationTokenSource();
            _socket = new ClientWebSocket();

            var uri = config.WebSocketUri(characterUuid, lastEventId);
            Debug.Log($"[WS] Connecting {uri}");
            await _socket.ConnectAsync(uri, _cts.Token);
            _ = ReceiveLoop(_cts.Token);
        }

        public void Pump()
        {
            while (_queue.TryDequeue(out var evt))
            {
                if (evt.id > _lastEventId)
                {
                    _lastEventId = evt.id;
                }

                if (evt.type == "connected")
                {
                    continue;
                }

                OnEvent?.Invoke(evt);
            }
        }

        public async Task DisconnectAsync()
        {
            if (_cts != null)
            {
                _cts.Cancel();
                _cts.Dispose();
                _cts = null;
            }

            if (_socket != null)
            {
                if (_socket.State == WebSocketState.Open)
                {
                    try
                    {
                        await _socket.CloseAsync(WebSocketCloseStatus.NormalClosure, "bye", CancellationToken.None);
                    }
                    catch
                    {
                        // ignore
                    }
                }

                _socket.Dispose();
                _socket = null;
            }
        }

        public void Dispose()
        {
            _ = DisconnectAsync();
        }

        private async Task ReceiveLoop(CancellationToken token)
        {
            var buffer = new byte[8192];

            while (!token.IsCancellationRequested && _socket?.State == WebSocketState.Open)
            {
                try
                {
                    var segment = new ArraySegment<byte>(buffer);
                    var result = await _socket.ReceiveAsync(segment, token);
                    if (result.MessageType == WebSocketMessageType.Close)
                    {
                        break;
                    }

                    var json = Encoding.UTF8.GetString(buffer, 0, result.Count);
                    var evt = JsonConvert.DeserializeObject<GameEventDto>(json);
                    if (evt != null)
                    {
                        _queue.Enqueue(evt);
                    }
                }
                catch (OperationCanceledException)
                {
                    break;
                }
                catch (Exception ex)
                {
                    Debug.LogWarning($"[WS] Receive error: {ex.Message}");
                    break;
                }
            }
        }
    }
}
