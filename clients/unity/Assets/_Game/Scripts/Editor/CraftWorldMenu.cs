#if UNITY_EDITOR
using CraftWorld.Client;
using CraftWorld.Client.UI;
using CraftWorld.Client.World;
using UnityEditor;
using UnityEditor.SceneManagement;
using UnityEngine;

namespace CraftWorld.Client.Editor
{
    public static class CraftWorldMenu
    {
        [MenuItem("CraftWorld/Create API Config Asset")]
        public static void CreateApiConfig()
        {
            const string dir = "Assets/_Game/Resources";
            if (!AssetDatabase.IsValidFolder(dir))
            {
                AssetDatabase.CreateFolder("Assets/_Game", "Resources");
            }

            var asset = ScriptableObject.CreateInstance<GameApiConfig>();
            AssetDatabase.CreateAsset(asset, $"{dir}/GameApiConfig.asset");
            AssetDatabase.SaveAssets();
            Selection.activeObject = asset;
            EditorUtility.DisplayDialog("CraftWorld", "GameApiConfig created. Set baseUrl to your server.", "OK");
        }

        [MenuItem("CraftWorld/Setup Bootstrap Scene")]
        public static void SetupBootstrapScene()
        {
            var scene = EditorSceneManager.NewScene(NewSceneSetup.DefaultGameObjects, NewSceneMode.Single);

            var bootstrap = new GameObject("GameBootstrap");
            bootstrap.AddComponent<GameSession>();
            bootstrap.AddComponent<LoginUi>();
            bootstrap.AddComponent<WorldHud>();
            bootstrap.AddComponent<WorldCoordinator>();

            var zoneRoot = new GameObject("ZoneRoot");
            zoneRoot.transform.SetParent(bootstrap.transform, false);
            var playersRoot = new GameObject("PlayersRoot");
            playersRoot.transform.SetParent(bootstrap.transform, false);

            var coordinator = bootstrap.GetComponent<WorldCoordinator>();
            var so = new SerializedObject(coordinator);
            so.FindProperty("zoneRoot").objectReferenceValue = zoneRoot.transform;
            so.FindProperty("playersRoot").objectReferenceValue = playersRoot.transform;
            so.ApplyModifiedPropertiesWithoutUndo();

            var cam = Camera.main;
            if (cam != null)
            {
                cam.gameObject.AddComponent<CameraFollow>();
            }

            const string scenePath = "Assets/_Game/Scenes/Bootstrap.unity";
            if (!AssetDatabase.IsValidFolder("Assets/_Game/Scenes"))
            {
                AssetDatabase.CreateFolder("Assets/_Game", "Scenes");
            }

            EditorSceneManager.SaveScene(scene, scenePath);
            EditorUtility.DisplayDialog("CraftWorld", $"Bootstrap scene saved to {scenePath}", "OK");
        }
    }
}
#endif
