using CraftWorld.Client.Network.Dto;
using UnityEngine;

namespace CraftWorld.Client.UI
{
    public sealed class WorldHud : MonoBehaviour
    {
        private bool _visible;
        private string _characterName;
        private WorldStateDto _state;

        public void Show(string characterName, WorldStateDto state)
        {
            _visible = true;
            _characterName = characterName;
            _state = state;
        }

        public void UpdateState(WorldStateDto state) => _state = state;

        private void OnGUI()
        {
            if (!_visible || _state == null)
            {
                return;
            }

            var rect = new Rect(12, 12, 320, 120);
            GUI.Box(rect, "World HUD");
            GUILayout.BeginArea(new Rect(rect.x + 8, rect.y + 24, rect.width - 16, rect.height - 32));
            GUILayout.Label($"{_characterName} — {_state.zone_name}");
            GUILayout.Label($"X {_state.x:F1}  Y {_state.y:F1}  Z {_state.z:F1}");
            GUILayout.Label("WASD — move, E — interact");
            GUILayout.EndArea();
        }
    }
}
