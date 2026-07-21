import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import DostLogo from '../../components/DostLogo';
import { IUser, ILock, IEye, IEyeOff, IArrow, IInfo, ISpinner } from '../../components/Icons';
import '../../../css/theme.css';
import '../../../css/login.css';

export default function Login() {
  const [showPassword, setShowPassword] = useState(false);

  const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
    remember: true,
  });

  const firstError = errors.email || errors.password;

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/login');
  };

  return (
    <div className="aslr-login">
      <Head title="Sign in" />

      {/* LEFT: designed cover panel */}
      <aside className="aslr-cover">
        <div className="aslr-cover__bg" aria-hidden="true">
          <span className="aslr-cover__ring aslr-cover__ring--tr" />
          <span className="aslr-cover__ring aslr-cover__ring--bl" />
          <span className="aslr-cover__dots" />
        </div>
        <div className="aslr-cover__center">
          <span className="aslr-cover__rule" />
          <h3 className="aslr-cover__ghost">ASLR SYSTEM</h3>
          <h4 className="aslr-cover__title">
            CARAGA<br />
            <span>REGION</span>
          </h4>
          <div className="aslr-cover__stats">
            <div>
              <span className="aslr-cover__stat-key">Efficiency</span>
              <span className="aslr-cover__stat-val">Automated Processing</span>
            </div>
            <span className="aslr-cover__stat-div" />
            <div>
              <span className="aslr-cover__stat-key">Transparency</span>
              <span className="aslr-cover__stat-val">Report Summaries</span>
            </div>
          </div>
        </div>

        <span className="aslr-cover__vertical">DEPARTMENT OF SCIENCE AND TECHNOLOGY</span>
      </aside>

      {/* RIGHT: auth panel */}
      <main className="aslr-panel">
        <div className="aslr-panel__body">
          <div className="aslr-panel__head">
            <div className="aslr-panel__mark">
              <DostLogo size={36} showText={false} />
            </div>
            <div>
              <h2 className="aslr-panel__org">DOST - CARAGA</h2>
              <p className="aslr-panel__org-sub">Regional Office XIII</p>
            </div>
          </div>

          <h1 className="aslr-panel__title">Automated Subsidiary Ledger and Report</h1>
          <p className="aslr-panel__lede">
            Secure ledger accounting, fund tracking, and compliance automation
          </p>

          <form onSubmit={submit} className="aslr-form">
            <div className="aslr-field">
              <label htmlFor="email">Email</label>
              <div className="aslr-input">
                <span className="aslr-input__icon"><IUser /></span>
                <input
                  id="email"
                  type="text"
                  autoComplete="username"
                  placeholder="admin.caraga@dost.gov.ph"
                  value={data.email}
                  onChange={(e) => setData('email', e.target.value)}
                />
              </div>
            </div>

            <div className="aslr-field">
              <div className="aslr-field__row">
                <label htmlFor="password">Password</label>
                {/* <a href="/forgot-password" className="aslr-link">Forgot Password?</a> */}
              </div>
              <div className="aslr-input">
                <span className="aslr-input__icon"><ILock /></span>
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  placeholder="••••••••••••"
                  value={data.password}
                  onChange={(e) => setData('password', e.target.value)}
                />
                <button
                  type="button"
                  className="aslr-input__toggle"
                  onClick={() => setShowPassword((s) => !s)}
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                >
                  {showPassword ? <IEyeOff /> : <IEye />}
                </button>
              </div>
            </div>
            {firstError && (
              <div className="aslr-alert" role="alert">
                <IInfo />
                <span>{firstError}</span>
              </div>
            )}
            {/* <div className="aslr-meta">
              <label className="aslr-check">
                <input
                  type="checkbox"
                  checked={data.remember}
                  onChange={(e) => setData('remember', e.target.checked)}
                />
                <span>Remember for 30 days</span>
              </label>
              <span className="aslr-ver">Ver. 2026.1</span>
            </div> */}

            <button type="submit" className="aslr-submit" disabled={processing}>
              {processing ? (
                <><ISpinner /><span>AUTHENTICATING…</span></>
              ) : (
                <><span>AUTHENTICATE</span><IArrow /></>
              )}
            </button>
          </form>
        </div>

        <footer className="aslr-panel__foot">
          <p>
            Official Internal System of the Department of Science and Technology - CARAGA Region.
            Unauthorized access is strictly prohibited and subject to legal action under R.A. 10175.
          </p>
        </footer>
      </main>
    </div>
  );
}