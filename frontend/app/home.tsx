import { router, type Href } from 'expo-router';
import { useState } from 'react';
import { ScrollView, StyleSheet } from 'react-native';
import { Snackbar } from 'react-native-paper';
import { AppScreenLayout } from '../components/layout/app-screen-layout';
import { ApplicationGrid } from '../components/menu/application-grid';
import { AppDrawer } from '../components/navigation/app-drawer';
import { getVisibleMenu, type MenuModule } from '../config/menu';

const APP_MODULES = getVisibleMenu();

export default function HomeScreen() {
  const [drawerVisible, setDrawerVisible] = useState(false);
  const [message, setMessage] = useState('');

  function openModule(module: MenuModule) {
    router.push({ pathname: '/module/[moduleId]', params: { moduleId: module.id } } as Href);
  }

  function openSettings() {
    setDrawerVisible(false);
    const settingsModule = APP_MODULES.find((module) => module.id === 'settings');
    if (settingsModule) openModule(settingsModule);
  }

  return (
    <AppScreenLayout
      onMenuPress={() => setDrawerVisible(true)}
      onNotificationsPress={() => setMessage('No tienes notificaciones nuevas')}
      title="Pacientes"
    >
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ApplicationGrid modules={APP_MODULES} onModulePress={openModule} />
      </ScrollView>

      <AppDrawer
        onClose={() => setDrawerVisible(false)}
        onHelpPress={() => {
          setDrawerVisible(false);
          setMessage('Ayuda estará disponible próximamente');
        }}
        onHomePress={() => setDrawerVisible(false)}
        onSettingsPress={openSettings}
        visible={drawerVisible}
      />

      <Snackbar duration={2600} onDismiss={() => setMessage('')} visible={Boolean(message)}>
        {message}
      </Snackbar>
    </AppScreenLayout>
  );
}

const styles = StyleSheet.create({
  content: {
    paddingBottom: 44,
  },
});
