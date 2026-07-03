using CraftWorld.Client.UI;
using CraftWorld.Client.World;
using UnityEngine;

namespace CraftWorld.Client
{
    /// <summary>
    /// Creates primitive player/interactable prefabs when Inspector references are empty.
    /// </summary>
    public static class RuntimePrefabFactory
    {
        public static LocalPlayerMotor CreateLocalPlayerPrefab()
        {
            var go = GameObject.CreatePrimitive(PrimitiveType.Capsule);
            go.name = "LocalPlayerPrefab";
            Object.Destroy(go.GetComponent<Collider>());
            var controller = go.AddComponent<CharacterController>();
            controller.height = 2f;
            controller.radius = 0.4f;
            controller.center = new Vector3(0f, 1f, 0f);
            go.AddComponent<LocalPlayerMotor>();
            go.SetActive(false);
            return go.GetComponent<LocalPlayerMotor>();
        }

        public static RemotePlayerAvatar CreateRemotePlayerPrefab()
        {
            var go = GameObject.CreatePrimitive(PrimitiveType.Capsule);
            go.name = "RemotePlayerPrefab";
            Object.Destroy(go.GetComponent<Collider>());
            go.transform.localScale = new Vector3(0.9f, 0.9f, 0.9f);
            go.GetComponent<Renderer>().material.color = new Color(0.3f, 0.8f, 0.9f);

            var labelGo = new GameObject("Label");
            labelGo.transform.SetParent(go.transform, false);
            labelGo.transform.localPosition = new Vector3(0f, 2.2f, 0f);
            var label = labelGo.AddComponent<TextMesh>();
            label.characterSize = 0.15f;
            label.anchor = TextAnchor.MiddleCenter;
            label.alignment = TextAlignment.Center;

            var avatar = go.AddComponent<RemotePlayerAvatar>();
            go.SetActive(false);
            return avatar;
        }

        public static InteractableProxy CreateInteractablePrefab()
        {
            var go = GameObject.CreatePrimitive(PrimitiveType.Cube);
            go.name = "InteractablePrefab";
            go.transform.localScale = new Vector3(1.2f, 1.2f, 1.2f);
            var col = go.GetComponent<Collider>();
            if (col != null)
            {
                col.isTrigger = true;
            }

            var labelGo = new GameObject("Label");
            labelGo.transform.SetParent(go.transform, false);
            labelGo.transform.localPosition = new Vector3(0f, 1.2f, 0f);
            var label = labelGo.AddComponent<TextMesh>();
            label.characterSize = 0.12f;
            label.anchor = TextAnchor.MiddleCenter;

            var proxy = go.AddComponent<InteractableProxy>();
            go.SetActive(false);
            return proxy;
        }
    }
}
