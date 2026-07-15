import { useState } from "react";
import { router, usePage, Head } from '@inertiajs/react';
import '../../../css/login.css';

export default function Login() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  
  const { errors } = usePage().props;
  const [submitting, setSubmitting] = useState(false);

  function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);

    router.post('/login', { email, password }, {
      onFinish: () => setSubmitting(false)
    });
  }

  return (
    <div className="talaan-page">
      <Head title="Sign In" />
      <div className="talaan-card">
        <div className="talaan-margin-rule" aria-hidden="true" />

        <div className="talaan-content">
          <div className="talaan-seal" aria-hidden="true">
            <span>T</span>
          </div>

          <h1 className="talaan-wordmark">Talaan</h1>

          <form className="talaan-form" onSubmit={handleSubmit} noValidate>
            <div className="talaan-field">
              <label htmlFor="email">Email address</label>
              <input
                id="email"
                type="email"
                autoComplete="username"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="name@agency.gov.ph"
              />
              {/* Server-side errors if email validation fails */}
              {errors.email && <p className="talaan-error" role="alert">{errors.email}</p>}
            </div>

            <div className="talaan-field">
              <label htmlFor="password">Password</label>
              <div className="talaan-password-wrap">
                <input
                  id="password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="current-password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Enter your password"
                />
                <button
                  type="button"
                  className="talaan-toggle-visibility"
                  onClick={() => setShowPassword((v) => !v)}
                  aria-label={showPassword ? "Hide password" : "Show password"}
                >
                  {showPassword ? "Hide" : "Show"}
                </button>
              </div>
              {errors.password && <p className="talaan-error" role="alert">{errors.password}</p>}
            </div>

            <button type="submit" className="talaan-submit" disabled={submitting}>
              {submitting ? "Signing in…" : "Sign in"}
            </button>
          </form>

          <p className="talaan-footer-note">
            Accounts must be provisioned by network administration.
          </p>
        </div>
      </div>
    </div>
  );
}