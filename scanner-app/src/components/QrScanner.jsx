import { useState, useRef, useEffect } from 'react';
import { Html5Qrcode } from 'html5-qrcode';

export default function QrScanner({ onScan }) {
  const [scanning, setScanning] = useState(false);
  const scannerRef = useRef(null);

  useEffect(() => {
    return () => {
      if (scannerRef.current) {
        scannerRef.current.stop().catch(() => {});
      }
    };
  }, []);

  const startScan = async () => {
    setScanning(true);
    const scanner = new Html5Qrcode('qr-reader');
    scannerRef.current = scanner;

    try {
      await scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
          scanner.stop();
          setScanning(false);
          onScan(decodedText);
        },
        () => {}
      );
    } catch (err) {
      setScanning(false);
      alert('Camera access denied. Use manual search.');
    }
  };

  const stopScan = async () => {
    if (scannerRef.current) {
      await scannerRef.current.stop();
    }
    setScanning(false);
  };

  return (
    <div>
      <div id="qr-reader" style={{ width: '100%' }} />
      {!scanning ? (
        <button onClick={startScan} style={styles.button}>
          Start Scanning
        </button>
      ) : (
        <button onClick={stopScan} style={{ ...styles.button, background: '#dc2626' }}>
          Stop Scanning
        </button>
      )}
    </div>
  );
}

const styles = {
  button: {
    width: '100%',
    padding: 16,
    fontSize: 18,
    background: '#1a56db',
    color: 'white',
    border: 'none',
    borderRadius: 8,
    cursor: 'pointer',
  },
};
